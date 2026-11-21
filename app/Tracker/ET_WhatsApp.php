<?php

/*
 * منقول من بلجن ووردبريس employee-tracker 2.9.1 — منطق العمل كما هو،
 * يعمل فوق Laravel عبر app/Support/helpers.php (لا اعتماد على ووردبريس).
 */

class ET_WhatsApp {

	/** إصدار Graph API المستخدم لإرسال الرسائل. إن أوقفت ميتا هذا الإصدار مستقبلاً
	 *  (تحدث كل سنتين تقريبًا)، يكفي تحديث هذا السطر وحده لإصدار أحدث. */
	const GRAPH_API_VERSION = 'v21.0';

	/**
	 * يرسل رسالة القالب المعتمد لرقم الإدارة عبر واتساب بزنس الرسمي من ميتا (Cloud API).
	 *
	 * @param string $message  نص الإشعار الكامل — يُمرَّر كمتغير القالب الوحيد {{1}}.
	 * @param bool   $blocking إذا true: الطلب حاجز (blocking) ويرجع الدالة مصفوفة تشخيصية
	 *                         تحتوي رد ميتا الفعلي. يُستخدم فقط من زر "رسالة تجريبية" في
	 *                         الإدارة حتى لا يبطّئ استجابة REST العادية للموظف.
	 * @return array|null مصفوفة تشخيص عند $blocking = true، وإلا null.
	 */
	public static function notify_admin( $message, $blocking = false ) {
		$settings = ET_Settings::get();

		if ( empty( $settings['whatsapp_notify_enabled'] ) ) {
			return $blocking ? array( 'ok' => false, 'error' => 'الإشعار غير مفعّل في الإعدادات.' ) : null;
		}

		$phone           = self::normalize_phone( $settings['whatsapp_admin_phone'] ?? '' );
		$phone_number_id = trim( $settings['whatsapp_phone_number_id'] ?? '' );
		$access_token    = trim( $settings['whatsapp_access_token'] ?? '' );
		$template_name   = trim( $settings['whatsapp_template_name'] ?? '' );
		$template_lang   = trim( $settings['whatsapp_template_lang'] ?? '' );

		if ( empty( $phone ) || empty( $phone_number_id ) || empty( $access_token ) || empty( $template_name ) || empty( $template_lang ) ) {
			return $blocking ? array( 'ok' => false, 'error' => 'يجب تعبئة كل حقول واتساب بزنس أولاً: رقم الإدارة، Phone Number ID، Access Token، اسم القالب، ولغة القالب.' ) : null;
		}

		$url = 'https://graph.facebook.com/' . self::GRAPH_API_VERSION . '/' . rawurlencode( $phone_number_id ) . '/messages';

		$payload = array(
			'messaging_product' => 'whatsapp',
			'to'                => $phone,
			'type'              => 'template',
			'template'          => array(
				'name'       => $template_name,
				'language'   => array( 'code' => $template_lang ),
				'components' => array(
					array(
						'type'       => 'body',
						'parameters' => array(
							array( 'type' => 'text', 'text' => $message ),
						),
					),
				),
			),
		);

		$response = wp_remote_post( $url, array(
			'timeout'   => $blocking ? 15 : 5,
			// إرسال غير حاجز حتى لا يبطّئ استجابة REST للموظف في حال تأخر رد ميتا
			'blocking'  => $blocking,
			'sslverify' => true,
			'headers'   => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
			),
			'body' => wp_json_encode( $payload ),
		) );

		if ( ! $blocking ) return null;

		if ( is_wp_error( $response ) ) {
			$diag = array( 'ok' => false, 'error' => 'فشل الاتصال: ' . $response->get_error_message() );
			error_log( '[ET_WhatsApp] ' . $diag['error'] );
			return $diag;
		}

		$code     = wp_remote_retrieve_response_code( $response );
		$raw_body = wp_remote_retrieve_body( $response );
		$parsed   = json_decode( $raw_body, true );

		$diag = array(
			'ok'   => ( $code >= 200 && $code < 300 && ! empty( $parsed['messages'][0]['id'] ) ),
			'code' => $code,
			'body' => $raw_body,
			'url'  => $url,
		);

		if ( ! $diag['ok'] ) {
			$diag['error'] = ! empty( $parsed['error']['message'] )
				? $parsed['error']['message'] . ( ! empty( $parsed['error']['error_data']['details'] ) ? ' — ' . $parsed['error']['error_data']['details'] : '' )
				: 'رد غير متوقع من واتساب (HTTP ' . $code . ').';
		}

		error_log( '[ET_WhatsApp] HTTP ' . $code . ' — ' . $raw_body );

		return $diag;
	}

	/** يحوّل الرقم إلى الصيغة الدولية التي يتطلبها Graph API (أرقام فقط، بدون + أو أصفار بداية) */
	private static function normalize_phone( $phone ) {
		$phone = preg_replace( '/[^0-9+]/', '', (string) $phone );
		return ltrim( $phone, '+' );
	}
}
