<?php
/**
 * Plugin Name: WooCommerce Order Step Indicator
 * Plugin URI: https://github.com/amirrezashf/woocommerce-order-step-indicator
 * Description: Step-by-step visual order progress indicator for WooCommerce orders with AJAX admin controls and order list status column.
 * Version: 1.0.0
 * Author: Amirreza Shayesteh Far
 * Author URI: https://amirrezaa.ir/
 * License: GPL v2 or later
 * Text Domain: woocommerce-order-step-indicator
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WOSI_Order_Steps {
	const META_KEY = '_wosi_step';
	const META_LOG = '_wosi_step_log';
	const LIST_COL = 'wosi_order_step';

	private static $steps = array(
		'product'    => 'صفحه محصول',
		'cart'       => 'سبد خرید',
		'checkout'   => 'تسویه حساب و پرداخت',
		'queue_init' => 'در صف پردازش اولیه',
		'queue_run'  => 'در حال انجام',
		'done'       => 'تکمیل سفارش',
	);

	private static $force_done_statuses = array(
		'completed',
		'delivery-by-motor',
		'delivery-by-post',
		'payment-done',
	);

	private static $editable_statuses = array(
		'processing',
		'mohem',
	);

	public function __construct() {
		add_action( 'woocommerce_before_thankyou', array( $this, 'render_steps_front_top' ), 0 );
		add_action( 'woocommerce_view_order', array( $this, 'render_steps_front_top' ), 0 );

		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_wosi_update_step', array( $this, 'ajax_update_step' ) );

		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_legacy_orders_column' ), 9999 );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_legacy_orders_column' ), 9999, 2 );

		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_hpos_orders_column' ), 9999 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_hpos_orders_column' ), 9999, 2 );

		add_action( 'woocommerce_order_status_changed', array( $this, 'on_status_changed' ), 10, 4 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'mark_done_on_completed' ) );
	}

	private static function is_force_done_status( $status ) {
		return in_array( $status, self::$force_done_statuses, true );
	}

	private static function is_editable_status( $status ) {
		return in_array( $status, self::$editable_statuses, true );
	}

	private static function current_step_for_order( WC_Order $order ) {
		$status = $order->get_status();

		if ( self::is_force_done_status( $status ) || 'completed' === $status ) {
			return 'done';
		}

		if ( self::is_editable_status( $status ) ) {
			$saved = $order->get_meta( self::META_KEY );

			if ( in_array( $saved, array( 'queue_init', 'queue_run', 'done' ), true ) ) {
				return $saved;
			}

			return 'queue_init';
		}

		return null;
	}

	private static function should_render_for_status( $status ) {
		return self::is_editable_status( $status ) || self::is_force_done_status( $status ) || 'completed' === $status;
	}

	private static function label_of( $key ) {
		return isset( self::$steps[ $key ] ) ? self::$steps[ $key ] : $key;
	}

	private static function short_label_of( $key ) {
		$map = array(
			'queue_init' => 'صف اولیه',
			'queue_run'  => 'در حال انجام',
			'done'       => 'تکمیل',
		);

		return isset( $map[ $key ] ) ? $map[ $key ] : self::label_of( $key );
	}

	private static function fa_digits( $str ) {
		$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );

		return str_replace( $en, $fa, $str );
	}

	private static function fa_datetime( $timestamp ) {
		if ( function_exists( 'jdate' ) ) {
			$offset   = (float) get_option( 'gmt_offset' );
			$local_ts = $timestamp + (int) ( $offset * HOUR_IN_SECONDS );
			$text     = jdate( 'j F، ساعت H:i', $local_ts );
		} else {
			$text = wp_date( 'j F، ساعت H:i', $timestamp );
		}

		return self::fa_digits( $text );
	}

	private static function add_log_manual( WC_Order $order, $old_step, $new_step, $user_id ) {
		$logs = $order->get_meta( self::META_LOG );

		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		$logs[] = array(
			'ts'      => time(),
			'old'     => $old_step,
			'new'     => $new_step,
			'user_id' => absint( $user_id ),
			'ctx'     => 'manual',
		);

		if ( count( $logs ) > 50 ) {
			$logs = array_slice( $logs, -50 );
		}

		$order->update_meta_data( self::META_LOG, $logs );
		$order->save();
	}

	private static function get_logs( WC_Order $order, $limit = 5 ) {
		$logs = $order->get_meta( self::META_LOG );

		if ( ! is_array( $logs ) || empty( $logs ) ) {
			return array();
		}

		return array_slice( array_reverse( $logs ), 0, $limit );
	}

	private static function get_list_options() {
		return array(
			'queue_init' => 'صف اولیه',
			'queue_run'  => 'در حال انجام',
			'done'       => 'تکمیل',
		);
	}

	private static function get_order_object( $order_or_id ) {
		if ( $order_or_id instanceof WC_Order ) {
			return $order_or_id;
		}

		$order_id = absint( $order_or_id );

		if ( ! $order_id ) {
			return false;
		}

		return wc_get_order( $order_id );
	}

	private static function render_list_column_control( WC_Order $order ) {
		$status  = $order->get_status();
		$current = self::current_step_for_order( $order );

		if ( self::is_editable_status( $status ) ) {
			$nonce         = wp_create_nonce( 'wosi_update_step_' . $order->get_id() );
			$options       = self::get_list_options();
			$current_label = self::short_label_of( $current );

			$html  = '<div class="wosi-order-step-cell wosi-order-step-cell--editable">';
			$html .= '<div class="wosi-order-step-current">استاتوس فعلی: <span>' . esc_html( $current_label ) . '</span></div>';
			$html .= '<select class="wosi-order-step-inline" data-order-id="' . esc_attr( $order->get_id() ) . '" data-nonce="' . esc_attr( $nonce ) . '" aria-label="' . esc_attr__( 'استاتوس سفارش', 'woocommerce-order-step-indicator' ) . '">';

			foreach ( $options as $key => $label ) {
				$html .= '<option value="' . esc_attr( $key ) . '" ' . selected( $current, $key, false ) . '>' . esc_html( $label ) . '</option>';
			}

			$html .= '</select>';
			$html .= '<span class="wosi-order-step-state" aria-hidden="true"></span>';
			$html .= '</div>';

			return $html;
		}

		if ( 'done' === $current ) {
			return '<div class="wosi-order-step-cell"><span class="wosi-order-step-badge is-done">تکمیل</span></div>';
		}

		return '<div class="wosi-order-step-cell"><span class="wosi-order-step-badge is-empty">—</span></div>';
	}

	private static function is_order_screen_single( $screen ) {
		if ( ! $screen ) {
			return false;
		}

		return in_array( $screen->id, array( 'shop_order', 'woocommerce_page_wc-orders' ), true );
	}

	private static function is_order_screen_list( $screen ) {
		if ( ! $screen ) {
			return false;
		}

		return in_array( $screen->id, array( 'edit-shop_order', 'woocommerce_page_wc-orders' ), true );
	}

	private static function flat_three_colors_css() {
		return '
		<style>
		.wosi-steps-wrap{
			direction:rtl;
			margin:8px 0 18px;
			padding:16px;
			border:3px dashed #6442fc;
			background:#ffffff;
			border-radius:12px;
		}
		.wosi-steps{
			display:flex;
			flex-wrap:wrap;
			align-items:center;
			gap:10px;
			width:100%;
		}
		.wosi-step{
			position:relative;
			flex:0 1 auto;
			min-width:120px;
			padding:11px 14px;
			border-radius:12px;
			border:1px solid #e5e7eb;
			font-size:15px;
			line-height:1.35;
			text-align:center;
		}
		.wosi-step.is-past{
			background:#eff6ff;
			border-color:#bfdbfe;
			color:#1d4ed8;
			font-weight:600;
		}
		.wosi-step.is-active{
			background:#fff7ed;
			border-color:#fed7aa;
			color:#c2410c;
			font-weight:700;
			box-shadow:0 0 0 3px rgba(249,115,22,0.12);
		}
		.wosi-step.is-future,
		.wosi-step.is-done{
			background:#ecfdf5;
			border-color:#a7f3d0;
			color:#047857;
			font-weight:600;
		}
		.wosi-arrow{
			display:inline-flex;
			align-items:center;
			justify-content:center;
			padding:0 6px;
			color:#111827;
			font-size:20px;
			line-height:1;
			font-weight:800;
		}
		@media (max-width:640px){
			.wosi-step{
				font-size:14px;
				min-width:110px;
				padding:10px 12px;
			}
			.wosi-arrow{
				font-size:19px;
			}
		}
		</style>';
	}

	private static function render_bar( $current_key ) {
		$html  = self::flat_three_colors_css();
		$html .= '<div class="wosi-steps-wrap"><div class="wosi-steps">';

		$order_keys = array_keys( self::$steps );
		$current_index = array_search( $current_key, $order_keys, true );

		foreach ( $order_keys as $i => $key ) {
			$label = self::$steps[ $key ];
			$class = 'wosi-step';

			if ( false !== $current_index ) {
				if ( $i < $current_index ) {
					$class .= ' is-past';
				} elseif ( $i === $current_index ) {
					$class .= 'done' === $key ? ' is-done' : ' is-active';
				} else {
					$class .= ' is-future';
				}
			}

			$html .= '<div class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</div>';

			if ( $i < count( $order_keys ) - 1 ) {
				$html .= '<span class="wosi-arrow">›</span>';
			}
		}

		$html .= '</div></div>';

		return $html;
	}

	public function render_steps_front_top( $order_id ) {
		if ( empty( $order_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$status = $order->get_status();

		if ( ! self::should_render_for_status( $status ) ) {
			return;
		}

		$current = self::current_step_for_order( $order );

		if ( ! $current ) {
			return;
		}

		echo self::render_bar( $current );
	}

	public function add_metabox() {
		$screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';

		add_meta_box(
			'wosi_order_steps',
			'استاتوس سفارش',
			array( $this, 'metabox_html' ),
			$screen,
			'side',
			'high'
		);
	}

	public function metabox_html( $post_or_order ) {
		$order = false;

		if ( $post_or_order instanceof WC_Order ) {
			$order = $post_or_order;
		} elseif ( is_object( $post_or_order ) && ! empty( $post_or_order->ID ) ) {
			$order = wc_get_order( $post_or_order->ID );
		}

		if ( ! $order ) {
			echo '<p>سفارش یافت نشد.</p>';
			return;
		}

		$order_id = $order->get_id();

		echo '<p style="margin:0 0 8px;font-size:12px;color:#374151;">تغییر وضعیت در این بخش تاثیری در «وضعیت اصلی سفارش» ندارد و صرفا به صورت نمادین به کاربر نمایش داده می‌شود.</p>';

		$status = $order->get_status();

		if ( ! self::is_editable_status( $status ) ) {
			echo '<p style="font-size:13px;line-height:1.7">این کنترل فقط وقتی نمایش داده می‌شود که وضعیت سفارش <b>در حال انجام (processing)</b> یا <b>مهم (mohem)</b> باشد.</p>';
			echo $this->render_logs_html( $order, true );
			return;
		}

		$current = self::current_step_for_order( $order );
		$nonce   = wp_create_nonce( 'wosi_update_step_' . $order_id );

		$options = array(
			'queue_init' => 'در صف پردازش اولیه',
			'queue_run'  => 'در حال انجام',
			'done'       => 'تکمیل سفارش',
		);

		echo '<div id="wosi-steps-admin" data-order-id="' . esc_attr( $order_id ) . '" data-nonce="' . esc_attr( $nonce ) . '">';
		echo '<fieldset style="display:grid;gap:8px">';

		foreach ( $options as $key => $label ) {
			printf(
				'<label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
					<input type="radio" name="wosi_step" value="%1$s" %2$s />
					<span>%3$s</span>
				</label>',
				esc_attr( $key ),
				checked( $current, $key, false ),
				esc_html( $label )
			);
		}

		echo '</fieldset>';
		echo '<p id="wosi-step-feedback" style="margin-top:8px;font-size:12px;color:#111827;display:none">در حال ذخیره‌سازی…</p>';
		echo '</div>';

		echo $this->render_logs_html( $order, true );
	}

	private function render_logs_html( WC_Order $order, $wrap = false ) {
		$logs = self::get_logs( $order, 5 );
		$html = $wrap ? '<div id="wosi-steps-logs">' : '';

		$html .= '<hr style="margin:12px 0;border:none;border-top:1px solid #e5e7eb">';
		$html .= '<div><strong style="display:block;margin-bottom:6px;">تاریخچه تغییرات گام‌ها</strong>';

		if ( empty( $logs ) ) {
			$html .= '<p style="font-size:12px;color:#6b7280;margin:0">هنوز تغییری ثبت نشده است.</p></div>';

			if ( $wrap ) {
				$html .= '</div>';
			}

			return $html;
		}

		$html .= '<ul style="margin:0;padding-right:18px;list-style:disc;">';

		foreach ( $logs as $row ) {
			$ts   = isset( $row['ts'] ) ? absint( $row['ts'] ) : time();
			$old  = self::label_of( $row['old'] );
			$new  = self::label_of( $row['new'] );
			$when = self::fa_datetime( $ts );

			$name = 'سیستم';

			if ( ! empty( $row['user_id'] ) ) {
				$user = get_user_by( 'id', absint( $row['user_id'] ) );

				if ( $user ) {
					$name = trim( $user->display_name ) !== '' ? $user->display_name : $user->user_login;
				}
			}

			$line = sprintf(
				'%s سفارش را از «%s» به «%s» در تاریخ %s تغییر داد.',
				esc_html( $name ),
				esc_html( $old ),
				esc_html( $new ),
				esc_html( $when )
			);

			$html .= '<li style="font-size:12.5px;color:#374151;margin:6px 0">' . $line . '</li>';
		}

		$html .= '</ul></div>';

		if ( $wrap ) {
			$html .= '</div>';
		}

		return $html;
	}

	public function enqueue_admin_assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen ) {
			return;
		}

		$is_single = self::is_order_screen_single( $screen ) && in_array( $hook, array( 'post.php', 'post-new.php', 'woocommerce_page_wc-orders' ), true );
		$is_list   = self::is_order_screen_list( $screen );

		if ( ! $is_single && ! $is_list ) {
			return;
		}

		wp_enqueue_script( 'jquery' );

		$css = '
		.manage-column.column-' . self::LIST_COL . ',
		.column-' . self::LIST_COL . '{
			width:60px !important;
			max-width:60px !important;
			min-width:60px !important;
			text-align:center;
		}
		.wosi-order-step-cell{
			display:flex;
			flex-direction:column;
			align-items:center;
			justify-content:center;
			gap:4px;
			min-height:34px;
			width:100%;
		}
		.wosi-order-step-current{
			width:100%;
			font-size:10px;
			line-height:1.35;
			color:#6b7280;
			text-align:center;
			word-break:break-word;
		}
		.wosi-order-step-current span{
			display:block;
			margin-top:1px;
			color:#111827;
			font-weight:600;
		}
		.wosi-order-step-inline{
			width:72px !important;
			min-width:72px !important;
			max-width:72px !important;
			height:24px !important;
			min-height:24px !important;
			padding:0 18px 0 4px !important;
			font-size:10px !important;
			line-height:1.1 !important;
			border-radius:5px !important;
		}
		.wosi-order-step-cell.is-saving .wosi-order-step-inline{
			opacity:.7;
		}
		.wosi-order-step-cell.is-success .wosi-order-step-inline{
			border-color:#16a34a !important;
			box-shadow:0 0 0 1px rgba(22,163,74,.12);
		}
		.wosi-order-step-cell.is-error .wosi-order-step-inline{
			border-color:#dc2626 !important;
			box-shadow:0 0 0 1px rgba(220,38,38,.12);
		}
		.wosi-order-step-state{
			display:inline-block;
			width:6px;
			height:6px;
			border-radius:50%;
			background:transparent;
			flex:0 0 6px;
		}
		.wosi-order-step-cell.is-saving .wosi-order-step-state{
			background:#f59e0b;
		}
		.wosi-order-step-cell.is-success .wosi-order-step-state{
			background:#16a34a;
		}
		.wosi-order-step-cell.is-error .wosi-order-step-state{
			background:#dc2626;
		}
		.wosi-order-step-badge{
			display:inline-flex;
			align-items:center;
			justify-content:center;
			min-width:48px;
			min-height:22px;
			padding:2px 6px;
			border-radius:999px;
			font-size:10px;
			line-height:1.2;
			white-space:nowrap;
			box-sizing:border-box;
		}
		.wosi-order-step-badge.is-done{
			background:#ecfdf5;
			border:1px solid #a7f3d0;
			color:#047857;
			font-weight:600;
		}
		.wosi-order-step-badge.is-empty{
			background:#f9fafb;
			border:1px solid #e5e7eb;
			color:#9ca3af;
		}';

		wp_register_style( 'wosi-order-steps-inline-style', false, array(), '1.0.0' );
		wp_enqueue_style( 'wosi-order-steps-inline-style' );
		wp_add_inline_style( 'wosi-order-steps-inline-style', $css );

		$ajax_url = esc_url_raw( admin_url( 'admin-ajax.php' ) );

		$inline = "
jQuery(function($){
	var ajaxUrl = '{$ajax_url}';

	var box = $('#wosi-steps-admin');
	if(box.length){
		var orderId = box.data('order-id');
		var nonce = box.data('nonce');
		var feedback = $('#wosi-step-feedback');
		var logsBox = $('#wosi-steps-logs');

		box.on('change','input[name=\"wosi_step\"]', function(){
			var val = $(this).val();
			feedback.text('در حال ذخیره‌سازی…').css('color','#111827').show();

			$.post(ajaxUrl, {
				action: 'wosi_update_step',
				order_id: orderId,
				step: val,
				_wpnonce: nonce
			}).done(function(resp){
				if(resp && resp.success){
					feedback.text('ذخیره شد ✓').css('color','#16a34a');
					if(resp.data && resp.data.logs_html && logsBox.length){
						logsBox.replaceWith(resp.data.logs_html);
					}
					setTimeout(function(){ feedback.fadeOut(200); }, 1200);
				}else{
					var msg = (resp && resp.data) ? resp.data : 'خطا در ذخیره‌سازی';
					feedback.text(msg).css('color','#dc2626');
				}
			}).fail(function(){
				feedback.text('خطای شبکه').css('color','#dc2626');
			});
		});
	}

	$(document).on('change', '.wosi-order-step-inline', function(){
		var select = $(this);
		var wrap = select.closest('.wosi-order-step-cell');
		var current = wrap.find('.wosi-order-step-current span');
		var prev = select.data('prev') || select.find('option:selected').val();
		var prevTxt = select.data('prevText') || select.find('option:selected').text();
		var val = select.val();
		var txt = select.find('option:selected').text();
		var nonce = select.data('nonce');
		var orderId = select.data('order-id');

		wrap.removeClass('is-success is-error').addClass('is-saving');

		$.post(ajaxUrl, {
			action: 'wosi_update_step',
			order_id: orderId,
			step: val,
			_wpnonce: nonce
		}).done(function(resp){
			wrap.removeClass('is-saving');

			if(resp && resp.success){
				wrap.addClass('is-success');
				select.data('prev', val);
				select.data('prevText', txt);
				if(current.length){
					current.text(txt);
				}
				setTimeout(function(){
					wrap.removeClass('is-success');
				}, 1200);
			}else{
				wrap.addClass('is-error');
				select.val(prev);
				if(current.length){
					current.text(prevTxt);
				}
				var msg = (resp && resp.data) ? resp.data : 'خطا در ذخیره‌سازی';
				if(window.console){
					console.warn(msg);
				}
				setTimeout(function(){
					wrap.removeClass('is-error');
				}, 1800);
			}
		}).fail(function(){
			wrap.removeClass('is-saving').addClass('is-error');
			select.val(prev);
			if(current.length){
				current.text(prevTxt);
			}
			setTimeout(function(){
				wrap.removeClass('is-error');
			}, 1800);
		});
	});

	$('.wosi-order-step-inline').each(function(){
		var select = $(this);
		var txt = select.find('option:selected').text();
		select.data('prev', select.val());
		select.data('prevText', txt);
	});
});
";

		wp_add_inline_script( 'jquery', $inline );
	}

	public function ajax_update_step() {
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

		if ( ! $order_id ) {
			wp_send_json_error( 'شناسه سفارش نامعتبر است.' );
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'wosi_update_step_' . $order_id ) ) {
			wp_send_json_error( 'توکن امنیتی منقضی شده است.' );
		}

		if ( ! current_user_can( 'edit_shop_order', $order_id ) ) {
			wp_send_json_error( 'دسترسی کافی ندارید.' );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_send_json_error( 'سفارش یافت نشد.' );
		}

		if ( ! self::is_editable_status( $order->get_status() ) ) {
			wp_send_json_error( 'فقط در وضعیت‌های مجاز قابل ویرایش است.' );
		}

		$step = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : '';

		if ( ! in_array( $step, array( 'queue_init', 'queue_run', 'done' ), true ) ) {
			wp_send_json_error( 'مقدار ارسالی معتبر نیست.' );
		}

		$old = $order->get_meta( self::META_KEY );

		if ( ! $old ) {
			$old = 'queue_init';
		}

		if ( $old !== $step ) {
			$order->update_meta_data( self::META_KEY, $step );
			$order->save();

			self::add_log_manual( $order, $old, $step, get_current_user_id() );
		}

		wp_send_json_success(
			array(
				'logs_html' => $this->render_logs_html( $order, true ),
			)
		);
	}

	public function add_legacy_orders_column( $columns ) {
		$columns[ self::LIST_COL ] = 'استاتوس سفارش';
		return $columns;
	}

	public function render_legacy_orders_column( $column, $post_id ) {
		if ( $column !== self::LIST_COL ) {
			return;
		}

		$order = wc_get_order( $post_id );

		if ( ! $order ) {
			echo '—';
			return;
		}

		echo self::render_list_column_control( $order );
	}

	public function add_hpos_orders_column( $columns ) {
		$columns[ self::LIST_COL ] = 'استاتوس سفارش';
		return $columns;
	}

	public function render_hpos_orders_column( $column, $order ) {
		if ( $column !== self::LIST_COL ) {
			return;
		}

		$order = self::get_order_object( $order );

		if ( ! $order ) {
			echo '—';
			return;
		}

		echo self::render_list_column_control( $order );
	}

	public function on_status_changed( $order_id, $old_status, $new_status, $order ) {
		if ( ! ( $order instanceof WC_Order ) ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order ) {
			return;
		}

		if ( self::is_editable_status( $new_status ) ) {
			$order->update_meta_data( self::META_KEY, 'queue_init' );
			$order->save();
			return;
		}

		if ( self::is_force_done_status( $new_status ) ) {
			$order->update_meta_data( self::META_KEY, 'done' );
			$order->save();
			return;
		}
	}

	public function mark_done_on_completed( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		if ( $order->get_meta( self::META_KEY ) !== 'done' ) {
			$order->update_meta_data( self::META_KEY, 'done' );
			$order->save();
		}
	}
}

new WOSI_Order_Steps();
