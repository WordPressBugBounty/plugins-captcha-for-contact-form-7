<?php

namespace f12_cf7_captcha {

	use f12_cf7_captcha\ui\UI_Manager;
	use f12_cf7_captcha\ui\UI_Page;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Audit Log Admin Page — shows all admin/system audit events
	 * with filtering, pagination, severity color-coding, and detail view.
	 * Data is fetched via WP REST API endpoints.
	 */
	class UI_AuditLog extends UI_Page {

		public function __construct( UI_Manager $UI_Manager ) {
			parent::__construct( $UI_Manager, 'f12-cf7-captcha-audit-log', 'Audit Log', 4 );
		}

		public function get_settings( $settings ): array {
			return $settings;
		}

		protected function the_sidebar( $slug, $page ) {
			?>
			<div class="box">
				<div class="section">
					<h2><?php esc_html_e( 'About Audit Log', 'captcha-for-contact-form-7' ); ?></h2>
					<p><?php esc_html_e( 'The audit log tracks all admin and system events: settings changes, cron jobs, API errors, plugin lifecycle, and more.', 'captcha-for-contact-form-7' ); ?></p>
					<p><?php esc_html_e( 'This log is always active and does not need to be enabled separately. Entries are automatically cleaned up after 90 days.', 'captcha-for-contact-form-7' ); ?></p>
				</div>
			</div>
			<?php
		}

		protected function the_content( $slug, $page, $settings ) {
			?>
			<!-- Summary Cards -->
			<div id="f12-audit-summary" style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px;">
				<div style="background:#f1f5f9; border-radius:12px; padding:20px; text-align:center;">
					<div id="f12-audit-total" style="font-size:36px; font-weight:700; color:#1e40af;">&mdash;</div>
					<div style="font-size:14px; color:#475569; margin-top:4px;"><?php esc_html_e( 'Total Events', 'captcha-for-contact-form-7' ); ?></div>
				</div>
				<div style="background:#fef9c3; border-radius:12px; padding:20px; text-align:center;">
					<div id="f12-audit-warnings" style="font-size:36px; font-weight:700; color:#92400e;">&mdash;</div>
					<div style="font-size:14px; color:#78350f; margin-top:4px;"><?php esc_html_e( 'Warnings', 'captcha-for-contact-form-7' ); ?></div>
				</div>
				<div style="background:#fee2e2; border-radius:12px; padding:20px; text-align:center;">
					<div id="f12-audit-errors" style="font-size:36px; font-weight:700; color:#b91c1c;">&mdash;</div>
					<div style="font-size:14px; color:#991b1b; margin-top:4px;"><?php esc_html_e( 'Errors', 'captcha-for-contact-form-7' ); ?></div>
				</div>
				<div style="background:#fce7f3; border-radius:12px; padding:20px; text-align:center;">
					<div id="f12-audit-critical" style="font-size:36px; font-weight:700; color:#9d174d;">&mdash;</div>
					<div style="font-size:14px; color:#831843; margin-top:4px;"><?php esc_html_e( 'Critical', 'captcha-for-contact-form-7' ); ?></div>
				</div>
			</div>

			<!-- Filters -->
			<div style="margin-bottom:20px; display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
				<label for="f12-audit-days" style="font-weight:600;"><?php esc_html_e( 'Time Range:', 'captcha-for-contact-form-7' ); ?></label>
				<select id="f12-audit-days" style="padding:6px 12px; border:1px solid #d1d5db; border-radius:6px;">
					<option value="7"><?php esc_html_e( '7 days', 'captcha-for-contact-form-7' ); ?></option>
					<option value="30"><?php esc_html_e( '30 days', 'captcha-for-contact-form-7' ); ?></option>
					<option value="90" selected><?php esc_html_e( '90 days', 'captcha-for-contact-form-7' ); ?></option>
				</select>

				<label for="f12-audit-type" style="font-weight:600; margin-left:12px;"><?php esc_html_e( 'Type:', 'captcha-for-contact-form-7' ); ?></label>
				<select id="f12-audit-type" style="padding:6px 12px; border:1px solid #d1d5db; border-radius:6px;">
					<option value=""><?php esc_html_e( 'All', 'captcha-for-contact-form-7' ); ?></option>
					<option value="settings_change"><?php esc_html_e( 'Settings', 'captcha-for-contact-form-7' ); ?></option>
					<option value="cron_run"><?php esc_html_e( 'Cron', 'captcha-for-contact-form-7' ); ?></option>
					<option value="activation"><?php esc_html_e( 'Activation', 'captcha-for-contact-form-7' ); ?></option>
					<option value="rate_limit"><?php esc_html_e( 'Rate Limit', 'captcha-for-contact-form-7' ); ?></option>
					<option value="api_error"><?php esc_html_e( 'API', 'captcha-for-contact-form-7' ); ?></option>
					<option value="db_error"><?php esc_html_e( 'Database', 'captcha-for-contact-form-7' ); ?></option>
					<option value="trial"><?php esc_html_e( 'Trial', 'captcha-for-contact-form-7' ); ?></option>
					<option value="i18n"><?php esc_html_e( 'i18n', 'captcha-for-contact-form-7' ); ?></option>
				</select>

				<label for="f12-audit-severity" style="font-weight:600; margin-left:12px;"><?php esc_html_e( 'Severity:', 'captcha-for-contact-form-7' ); ?></label>
				<select id="f12-audit-severity" style="padding:6px 12px; border:1px solid #d1d5db; border-radius:6px;">
					<option value=""><?php esc_html_e( 'All', 'captcha-for-contact-form-7' ); ?></option>
					<option value="info"><?php esc_html_e( 'Info', 'captcha-for-contact-form-7' ); ?></option>
					<option value="warning"><?php esc_html_e( 'Warning', 'captcha-for-contact-form-7' ); ?></option>
					<option value="error"><?php esc_html_e( 'Error', 'captcha-for-contact-form-7' ); ?></option>
					<option value="critical"><?php esc_html_e( 'Critical', 'captcha-for-contact-form-7' ); ?></option>
				</select>
			</div>

			<!-- Audit Log Table -->
			<div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:20px;">
				<h3 style="margin:0 0 16px 0; font-size:16px;"><?php esc_html_e( 'Audit Log', 'captcha-for-contact-form-7' ); ?></h3>
				<table class="widefat striped" id="f12-audit-log-table" style="border:0;">
					<thead>
						<tr>
							<th style="width:150px;"><?php esc_html_e( 'Time', 'captcha-for-contact-form-7' ); ?></th>
							<th style="width:100px;"><?php esc_html_e( 'Type', 'captcha-for-contact-form-7' ); ?></th>
							<th style="width:80px;"><?php esc_html_e( 'Severity', 'captcha-for-contact-form-7' ); ?></th>
							<th><?php esc_html_e( 'Code', 'captcha-for-contact-form-7' ); ?></th>
							<th><?php esc_html_e( 'Description', 'captcha-for-contact-form-7' ); ?></th>
							<th style="width:70px;"><?php esc_html_e( 'User', 'captcha-for-contact-form-7' ); ?></th>
							<th style="width:60px;"><?php esc_html_e( 'Detail', 'captcha-for-contact-form-7' ); ?></th>
						</tr>
					</thead>
					<tbody id="f12-audit-log-body">
						<tr><td colspan="7" style="text-align:center; padding:40px; color:#94a3b8;">
							<?php esc_html_e( 'Loading...', 'captcha-for-contact-form-7' ); ?>
						</td></tr>
					</tbody>
				</table>
				<div id="f12-audit-log-pagination" style="display:flex; justify-content:space-between; align-items:center; margin-top:12px;">
					<span id="f12-audit-page-info" style="color:#64748b; font-size:13px;"></span>
					<div style="display:flex; gap:8px;">
						<button id="f12-audit-prev" class="button" disabled><?php esc_html_e( 'Previous', 'captcha-for-contact-form-7' ); ?></button>
						<button id="f12-audit-next" class="button" disabled><?php esc_html_e( 'Next', 'captcha-for-contact-form-7' ); ?></button>
					</div>
				</div>
			</div>

			<!-- Detail Overlay -->
			<div id="f12-audit-detail-overlay" style="display:none; position:fixed; inset:0; z-index:100000; background:rgba(0,0,0,0.5);" onclick="if(event.target===this)this.style.display='none'">
				<div style="position:absolute; right:0; top:0; bottom:0; width:520px; background:#fff; box-shadow:-4px 0 24px rgba(0,0,0,0.15); overflow-y:auto; padding:24px;">
					<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
						<h3 style="margin:0; font-size:18px;"><?php esc_html_e( 'Audit Event Detail', 'captcha-for-contact-form-7' ); ?></h3>
						<button onclick="document.getElementById('f12-audit-detail-overlay').style.display='none'" style="background:none; border:none; cursor:pointer; font-size:20px; color:#64748b;">&times;</button>
					</div>
					<div id="f12-audit-detail-content"></div>
				</div>
			</div>

			<script>
			(function(){
				var API_BASE = '<?php echo esc_js( rest_url( 'f12-cf7-captcha/v1/audit' ) ); ?>';
				var NONCE    = '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>';
				var LOG_LIMIT = 50;
				var logOffset = 0;
				var logTotal  = 0;
				var currentDays = 90;
				var currentType = '';
				var currentSeverity = '';
				var logEntries = [];

				var SEVERITY_COLORS = {
					info:     {bg:'#f1f5f9', text:'#475569', border:'#cbd5e1'},
					warning:  {bg:'#fef9c3', text:'#92400e', border:'#fde68a'},
					error:    {bg:'#fee2e2', text:'#b91c1c', border:'#fca5a5'},
					critical: {bg:'#fce7f3', text:'#9d174d', border:'#f9a8d4'}
				};

				var TYPE_LABELS = {
					settings_change: '<?php echo esc_js( __( 'Settings', 'captcha-for-contact-form-7' ) ); ?>',
					cron_run:        '<?php echo esc_js( __( 'Cron', 'captcha-for-contact-form-7' ) ); ?>',
					activation:      '<?php echo esc_js( __( 'Activation', 'captcha-for-contact-form-7' ) ); ?>',
					rate_limit:      '<?php echo esc_js( __( 'Rate Limit', 'captcha-for-contact-form-7' ) ); ?>',
					api_error:       '<?php echo esc_js( __( 'API', 'captcha-for-contact-form-7' ) ); ?>',
					db_error:        '<?php echo esc_js( __( 'Database', 'captcha-for-contact-form-7' ) ); ?>',
					trial:           '<?php echo esc_js( __( 'Trial', 'captcha-for-contact-form-7' ) ); ?>',
					i18n:            '<?php echo esc_js( __( 'i18n', 'captcha-for-contact-form-7' ) ); ?>'
				};

				function apiFetch(endpoint, params) {
					var url = API_BASE + '/' + endpoint;
					var qs = Object.keys(params||{}).filter(function(k){return params[k]!==''&&params[k]!==null}).map(function(k){return k+'='+encodeURIComponent(params[k])}).join('&');
					if(qs) url += '?' + qs;
					return fetch(url, {headers:{'X-WP-Nonce':NONCE}}).then(function(r){return r.json()});
				}

				function getFilters() {
					return {days:currentDays, type:currentType, severity:currentSeverity};
				}

				function loadAll() {
					var f = getFilters();
					apiFetch('summary', f).then(renderSummary);
					loadLog(0);
				}

				function renderSummary(d) {
					document.getElementById('f12-audit-total').textContent = (d.total||0).toLocaleString();
					document.getElementById('f12-audit-warnings').textContent = (d.warnings||0).toLocaleString();
					document.getElementById('f12-audit-errors').textContent = (d.errors||0).toLocaleString();
					document.getElementById('f12-audit-critical').textContent = (d.critical||0).toLocaleString();
				}

				function loadLog(offset) {
					logOffset = offset;
					var f = getFilters();
					f.limit = LOG_LIMIT;
					f.offset = offset;
					apiFetch('entries', f).then(renderLog);
				}

				function escHtml(s) {
					var d = document.createElement('div');
					d.textContent = s;
					return d.innerHTML;
				}

				function renderLog(d) {
					logEntries = d.data || [];
					logTotal = d.total || 0;
					var tbody = document.getElementById('f12-audit-log-body');

					if(!logEntries.length) {
						tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:40px; color:#94a3b8;"><?php echo esc_js( __( 'No audit events recorded yet.', 'captcha-for-contact-form-7' ) ); ?></td></tr>';
					} else {
						var html = '';
						logEntries.forEach(function(e, idx) {
							var sc = SEVERITY_COLORS[e.severity] || SEVERITY_COLORS.info;
							var typeLabel = TYPE_LABELS[e.event_type] || e.event_type;
							var ts = new Date(e.ts + 'Z').toLocaleString();
							var userStr = e.user_id ? '#'+e.user_id : '—';

							html += '<tr style="cursor:pointer;" onclick="window.f12ShowAuditDetail('+idx+')">';
							html += '<td style="white-space:nowrap; font-size:12px;">'+escHtml(ts)+'</td>';
							html += '<td><span style="display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; background:#e0e7ff; color:#3730a3;">'+escHtml(typeLabel)+'</span></td>';
							html += '<td><span style="display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; background:'+sc.bg+'; color:'+sc.text+'; border:1px solid '+sc.border+';">'+escHtml(e.severity)+'</span></td>';
							html += '<td style="font-size:12px; font-family:monospace;">'+escHtml(e.event_code)+'</td>';
							html += '<td style="font-size:12px; max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="'+escHtml(e.description)+'">'+escHtml(e.description)+'</td>';
							html += '<td style="font-size:12px;">'+escHtml(userStr)+'</td>';
							html += '<td><button class="button button-small" onclick="event.stopPropagation();window.f12ShowAuditDetail('+idx+')"><?php echo esc_js( __( 'View', 'captcha-for-contact-form-7' ) ); ?></button></td>';
							html += '</tr>';
						});
						tbody.innerHTML = html;
					}

					// Pagination
					var totalPages = Math.max(1, Math.ceil(logTotal / LOG_LIMIT));
					var currentPage = Math.floor(logOffset / LOG_LIMIT) + 1;
					document.getElementById('f12-audit-page-info').textContent = '<?php echo esc_js( __( 'Page', 'captcha-for-contact-form-7' ) ); ?> ' + currentPage + ' / ' + totalPages + ' (' + logTotal + ' <?php echo esc_js( __( 'entries', 'captcha-for-contact-form-7' ) ); ?>)';
					document.getElementById('f12-audit-prev').disabled = (logOffset <= 0);
					document.getElementById('f12-audit-next').disabled = (logOffset + LOG_LIMIT >= logTotal);
				}

				window.f12ShowAuditDetail = function(idx) {
					var e = logEntries[idx];
					if(!e) return;
					var overlay = document.getElementById('f12-audit-detail-overlay');
					var content = document.getElementById('f12-audit-detail-content');

					var sc = SEVERITY_COLORS[e.severity] || SEVERITY_COLORS.info;
					var typeLabel = TYPE_LABELS[e.event_type] || e.event_type;
					var ts = new Date(e.ts + 'Z').toLocaleString();

					var html = '';

					// Header info grid
					html += '<div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:20px;">';
					html += '<div><div style="font-size:12px; color:#64748b;"><?php echo esc_js( __( 'Type', 'captcha-for-contact-form-7' ) ); ?></div><span style="display:inline-block; padding:2px 8px; border-radius:10px; font-size:12px; font-weight:600; background:#e0e7ff; color:#3730a3; margin-top:4px;">'+escHtml(typeLabel)+'</span></div>';
					html += '<div><div style="font-size:12px; color:#64748b;"><?php echo esc_js( __( 'Severity', 'captcha-for-contact-form-7' ) ); ?></div><span style="display:inline-block; padding:2px 8px; border-radius:10px; font-size:12px; font-weight:600; background:'+sc.bg+'; color:'+sc.text+'; border:1px solid '+sc.border+'; margin-top:4px;">'+escHtml(e.severity)+'</span></div>';
					html += '<div><div style="font-size:12px; color:#64748b;"><?php echo esc_js( __( 'Time', 'captcha-for-contact-form-7' ) ); ?></div><div style="font-size:13px; margin-top:4px;">'+escHtml(ts)+'</div></div>';
					html += '<div><div style="font-size:12px; color:#64748b;"><?php echo esc_js( __( 'User', 'captcha-for-contact-form-7' ) ); ?></div><div style="font-size:13px; margin-top:4px;">'+(e.user_id ? '#'+e.user_id : '<?php echo esc_js( __( 'System', 'captcha-for-contact-form-7' ) ); ?>')+'</div></div>';
					html += '</div>';

					// Event code
					html += '<div style="margin-bottom:16px;">';
					html += '<div style="font-size:12px; color:#64748b; margin-bottom:4px;"><?php echo esc_js( __( 'Event Code', 'captcha-for-contact-form-7' ) ); ?></div>';
					html += '<div style="font-family:monospace; font-size:13px; background:#f1f5f9; padding:6px 10px; border-radius:6px;">'+escHtml(e.event_code)+'</div>';
					html += '</div>';

					// Description
					html += '<div style="margin-bottom:16px;">';
					html += '<div style="font-size:12px; color:#64748b; margin-bottom:4px;"><?php echo esc_js( __( 'Description', 'captcha-for-contact-form-7' ) ); ?></div>';
					html += '<div style="font-size:13px; background:#fefce8; padding:8px 12px; border-radius:6px; border:1px solid #fde68a;">'+escHtml(e.description)+'</div>';
					html += '</div>';

					// Context (JSON)
					if(e.context) {
						try {
							var ctx = typeof e.context === 'string' ? JSON.parse(e.context) : e.context;
							html += '<div style="margin-bottom:16px;">';
							html += '<div style="font-size:12px; color:#64748b; margin-bottom:4px;"><?php echo esc_js( __( 'Context', 'captcha-for-contact-form-7' ) ); ?></div>';
							html += '<pre style="font-size:12px; background:#f8fafc; padding:10px 12px; border-radius:6px; border:1px solid #e2e8f0; overflow-x:auto; white-space:pre-wrap; word-break:break-all; max-height:300px;">'+escHtml(JSON.stringify(ctx, null, 2))+'</pre>';
							html += '</div>';
						} catch(ex){}
					}

					// IP hash
					if(e.ip_hash) {
						html += '<div style="border-top:1px solid #e2e8f0; padding-top:12px; margin-top:16px;">';
						html += '<div style="font-size:11px; color:#94a3b8;">IP Hash: '+escHtml(e.ip_hash.substring(0,16))+'...</div>';
						html += '</div>';
					}

					content.innerHTML = html;
					overlay.style.display = 'block';
				};

				// Event listeners
				document.getElementById('f12-audit-days').addEventListener('change', function(){
					currentDays = parseInt(this.value);
					loadAll();
				});
				document.getElementById('f12-audit-type').addEventListener('change', function(){
					currentType = this.value;
					loadAll();
				});
				document.getElementById('f12-audit-severity').addEventListener('change', function(){
					currentSeverity = this.value;
					loadAll();
				});
				document.getElementById('f12-audit-prev').addEventListener('click', function(){
					loadLog(Math.max(0, logOffset - LOG_LIMIT));
				});
				document.getElementById('f12-audit-next').addEventListener('click', function(){
					loadLog(logOffset + LOG_LIMIT);
				});

				// Initial load
				loadAll();
			})();
			</script>
			<?php
		}
	}
}
