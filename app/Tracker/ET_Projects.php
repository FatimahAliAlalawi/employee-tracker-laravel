<?php

/*
 * منقول من بلجن ووردبريس employee-tracker 2.9.1 — منطق العمل كما هو،
 * يعمل فوق Laravel عبر app/Support/helpers.php (لا اعتماد على ووردبريس).
 */

class ET_Projects {

	public static function create( $title, $description = '' ) {
		global $wpdb;
		$table = ET_Database::table( 'projects' );
		$wpdb->insert( $table, array(
			'title'       => $title,
			'description' => $description,
			'status'      => 'active',
			'created_at'  => current_time( 'mysql' ),
		) );
		return (int) $wpdb->insert_id;
	}

	public static function get( $id ) {
		global $wpdb;
		$table = ET_Database::table( 'projects' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) );
	}

	/** كل المشاريع مع عدد مهامها ونسبة إنجازها الإجمالية (متوسط نسب مهامها) — لعرض قائمة تبويب "المشاريع" */
	public static function all_with_summary() {
		global $wpdb;
		$table       = ET_Database::table( 'projects' );
		$tasks_table = ET_Database::table( 'weekly_tasks' );

		$projects = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" );
		foreach ( $projects as $p ) {
			$tasks = $wpdb->get_results( $wpdb->prepare( "SELECT status, progress_percent FROM {$tasks_table} WHERE project_id = %d", $p->id ) );
			$p->tasks_count = count( $tasks );
			$p->done_count  = count( array_filter( $tasks, function ( $t ) { return $t->status === 'done'; } ) );
			$p->progress_percent = $tasks ? (int) round( array_sum( wp_list_pluck( $tasks, 'progress_percent' ) ) / count( $tasks ) ) : 0;
		}
		return $projects;
	}

	/** يربط مهمة موجودة بمشروع في ترتيب مُحدَّد؛ إن لم يُحدَّد ترتيب، تُوضع بنهاية السلسلة تلقائيًا */
	public static function link_task( $project_id, $task_id, $sequence_order = null ) {
		global $wpdb;
		$tasks_table = ET_Database::table( 'weekly_tasks' );

		if ( $sequence_order === null ) {
			$max = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(sequence_order) FROM {$tasks_table} WHERE project_id = %d", (int) $project_id ) );
			$sequence_order = $max + 1;
		}

		$wpdb->update( $tasks_table, array(
			'project_id'     => (int) $project_id,
			'sequence_order' => (int) $sequence_order,
		), array( 'id' => (int) $task_id ) );
	}

	public static function unlink_task( $task_id ) {
		global $wpdb;
		$tasks_table = ET_Database::table( 'weekly_tasks' );
		$wpdb->update( $tasks_table, array( 'project_id' => null, 'sequence_order' => null ), array( 'id' => (int) $task_id ) );
	}

	/** سلسلة مهام المشروع بالترتيب، مع حالة كل مهمة على "خريطة سير العمل": مكتملة / متوقفة (فيها مشكلة) /
	 *  معلّقة (بانتظار المهمة السابقة) / قيد التنفيذ / لم تبدأ — هذا ما يُبنى منه العرض البصري للسلسلة. */
	public static function chain_for_project( $project_id ) {
		global $wpdb;
		$tasks_table = ET_Database::table( 'weekly_tasks' );
		$emp         = ET_Database::table( 'employees' );

		$tasks = $wpdb->get_results( $wpdb->prepare(
			"SELECT t.*, e.name AS employee_name FROM {$tasks_table} t LEFT JOIN {$emp} e ON e.id = t.employee_id
			 WHERE t.project_id = %d ORDER BY t.sequence_order ASC, t.id ASC",
			(int) $project_id
		) );

		$ids           = wp_list_pluck( $tasks, 'id' );
		$latest_update = ET_TaskUpdates::latest_for_tasks( $ids );
		$members_map   = ET_TaskMembers::for_tasks( $ids );

		$chain = array();
		$previous_done = true; // أول مهمة بالسلسلة لا تنتظر شيئًا
		foreach ( $tasks as $t ) {
			$latest  = $latest_update[ (int) $t->id ] ?? null;
			$members = $members_map[ (int) $t->id ] ?? array();
			$is_done = $t->status === 'done';

			if ( ! $previous_done ) {
				$node_state  = 'blocked';
				$node_reason = 'معلّقة بسبب عدم اكتمال المهمة السابقة';
			} elseif ( $is_done ) {
				$node_state  = 'done';
				$node_reason = '';
			} elseif ( $latest && $latest->has_problem && $latest->problem_description ) {
				$node_state  = 'stuck';
				$node_reason = $latest->problem_description;
			} elseif ( (int) $t->progress_percent > 0 ) {
				$node_state  = 'in_progress';
				$node_reason = '';
			} else {
				$node_state  = 'not_started';
				$node_reason = '';
			}

			$chain[] = array(
				'id'               => (int) $t->id,
				'title'            => $t->title,
				'status'           => $t->status,
				'progress_percent' => (int) $t->progress_percent,
				'employee_name'    => $t->employee_name ?: '—',
				'members'          => array_map( function ( $m ) {
					return array( 'name' => $m->employee_name ?: '—', 'progress_percent' => (int) $m->progress_percent );
				}, $members ),
				'node_state'       => $node_state,
				'node_reason'      => $node_reason,
			);

			// المهمة التالية "معلّقة" إن لم تكن هذه المهمة مكتملة، بصرف النظر عن سبب عدم اكتمالها
			$previous_done = $is_done;
		}

		return $chain;
	}

	/** خريطة id←title لمجموعة مشاريع دفعة واحدة — تُستخدم بقوائم المهام لعرض اسم المشروع الذي تتبعه كل مهمة */
	public static function titles_map( $project_ids ) {
		global $wpdb;
		$project_ids = array_filter( array_unique( array_map( 'intval', (array) $project_ids ) ) );
		if ( empty( $project_ids ) ) return array();
		$table        = ET_Database::table( 'projects' );
		$placeholders = implode( ',', array_fill( 0, count( $project_ids ), '%d' ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, title FROM {$table} WHERE id IN ({$placeholders})", $project_ids ) );
		$out = array();
		foreach ( $rows as $r ) { $out[ (int) $r->id ] = $r->title; }
		return $out;
	}
}
