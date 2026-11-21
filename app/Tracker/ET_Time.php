<?php

/*
 * منقول من بلجن ووردبريس employee-tracker 2.9.1 — منطق العمل كما هو،
 * يعمل فوق Laravel عبر app/Support/helpers.php (لا اعتماد على ووردبريس).
 */

class ET_Time {

	/** يحوّل وقتًا محليًا (بتوقيت الموقع، مثل "2026-11-02 09:00" أو قيمة DATETIME محفوظة) إلى طابع Unix حقيقي، أو false */
	public static function ts( $local ) {
		$local = trim( (string) $local );
		if ( $local === '' ) return false;
		try {
			return ( new DateTimeImmutable( $local, wp_timezone() ) )->getTimestamp();
		} catch ( Exception $e ) {
			return false;
		}
	}

	/** التاريخ/الوقت الحالي بتوقيت الموقع بالصيغة المطلوبة، مع إزاحة اختيارية (مثل '-1 day') */
	public static function date( $format, $modify = '' ) {
		$dt = new DateTimeImmutable( 'now', wp_timezone() );
		if ( $modify !== '' ) $dt = $dt->modify( $modify );
		return $dt->format( $format );
	}
}
