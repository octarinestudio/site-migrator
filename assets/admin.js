/**
 * Site Migrator admin UI.
 *
 * @package Site_Migrator
 * @copyright Copyright (c) 2026 Octarine Studio
 * @license GPL-3.0-or-later
 * @link https://octarinestudio.uk/wordpress-site-migrator
 */
/* global smig, jQuery */
(function ($) {
	'use strict';

	var state = {
		sourceUrl: '',
		sourceAuth: '',
		sessionId: '',
		verified: false,
		downloadComplete: false,
		migrationActive: false,
	};

	function setMigrationActive(on) {
		state.migrationActive = !!on;
	}

	function setupBeforeUnload() {
		window.addEventListener('beforeunload', function (e) {
			if (!state.migrationActive) {
				return;
			}
			e.preventDefault();
			e.returnValue = smig.leave_warning || '';
			return e.returnValue;
		});
	}

	function copyToClipboard(text) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			return navigator.clipboard.writeText(text);
		}
		var tmp = document.createElement('textarea');
		tmp.value = text;
		document.body.appendChild(tmp);
		tmp.select();
		document.execCommand('copy');
		document.body.removeChild(tmp);
		return $.Deferred().resolve().promise();
	}

	function showNotice(type, message) {
		var $wrap = $('#smig-notices');
		$wrap.html(
			'<div class="notice notice-' +
				type +
				' is-dismissible"><p>' +
				escapeHtml(message) +
				'</p></div>'
		);
		$wrap.find('.notice').on('click', '.notice-dismiss', function () {
			$(this).closest('.notice').remove();
		});
	}

	function hideNotices() {
		$('#smig-notices').empty();
	}

	function setSpinner(id, on) {
		$('#' + id).toggleClass('is-active', on);
	}

	function setButtonLoading($btn, spinnerId, on) {
		$btn.prop('disabled', on);
		if (spinnerId) {
			setSpinner(spinnerId, on);
		}
	}

	function setStep(n) {
		$('.smig-step-panel').removeClass('active');
		$('#smig-step-' + n).addClass('active');
		$('#smig-step-nav .nav-tab').each(function () {
			var s = parseInt($(this).data('step'), 10);
			$(this).toggleClass('nav-tab-active', s === n);
		});
	}

	function ajax(action, data) {
		data = data || {};
		data.action = action;
		data.nonce = smig.nonce;
		return $.post(smig.ajax_url, data);
	}

	function formatBytes(bytes) {
		if (!bytes || bytes < 1024) {
			return bytes + ' B';
		}
		if (bytes < 1048576) {
			return (bytes / 1024).toFixed(1) + ' KB';
		}
		return (bytes / 1048576).toFixed(1) + ' MB';
	}

	function escapeHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function renderManifest(manifest, stats) {
		stats = stats || {};
		var tables = manifest.tables || [];
		var totalRows = 0;
		tables.forEach(function (t) {
			totalRows += t.rows || 0;
		});

		var html =
			'<table class="widefat striped"><thead><tr><th scope="col">Item</th><th scope="col">Details</th></tr></thead><tbody>';
		html +=
			'<tr><td>Database</td><td>' +
			tables.length +
			' tables, ' +
			totalRows.toLocaleString() +
			' rows</td></tr>';

		var ft = manifest.file_types || {};
		if (ft.uploads) {
			html +=
				'<tr><td>Uploads</td><td>' +
				ft.uploads.count +
				' files (' +
				formatBytes(ft.uploads.size) +
				')</td></tr>';
		}
		if (ft.theme) {
			html +=
				'<tr><td>Theme</td><td>' +
				escapeHtml(ft.theme.name || ft.theme.stylesheet) +
				' — ' +
				ft.theme.count +
				' files</td></tr>';
		}
		if (ft.plugins) {
			html +=
				'<tr><td>Plugins (source)</td><td>' +
				ft.plugins.count +
				' files (' +
				formatBytes(ft.plugins.size) +
				')</td></tr>';
		}
		if (stats.files !== undefined) {
			html +=
				'<tr><td>Files to pull</td><td>' +
				stats.files +
				' files</td></tr>';
		}
		if (stats.wporg_count) {
			html +=
				'<tr><td>WordPress.org</td><td>' +
				stats.wporg_count +
				' plugins will install from wordpress.org on apply</td></tr>';
		}
		if (stats.skipped_count) {
			html +=
				'<tr><td>Skipped</td><td>' +
				stats.skipped_count +
				' plugins already at same version on this site</td></tr>';
		}
		if (stats.plugin_files_removed) {
			html +=
				'<tr><td>Plugin files skipped</td><td>' +
				stats.plugin_files_removed.toLocaleString() +
				' files not downloaded</td></tr>';
		}
		html += '</tbody></table>';

		$('#smig-manifest').html(
			'<div class="notice notice-info inline"><p><strong>Ready to download</strong></p></div>' +
				html
		);
	}

	function setDownloadRunning(on) {
		$('#smig-dl-actions-start').prop('hidden', on);
		$('#smig-dl-actions-running').prop('hidden', !on);
		$('.smig-options').prop('hidden', on);
	}

	function setApplyRunning(on) {
		$('#smig-apply-actions-start').prop('hidden', on);
		$('#smig-apply-actions-running').prop('hidden', !on);
	}

	function resetWizard() {
		state.sessionId = '';
		state.downloadComplete = false;
		setMigrationActive(false);
		setDownloadRunning(false);
		setApplyRunning(false);
		$('#smig-dl-progress').prop('hidden', true);
		$('#smig-apply-progress').prop('hidden', true);
		$('#smig-complete').prop('hidden', true);
		$('#smig-dl-bar').val(0);
		$('#smig-apply-bar').val(0);
		$('#smig-manifest').empty();
		$('#smig-apply-summary').empty();
		setStep(state.verified ? 2 : 1);
	}

	function cancelMigration($btn, spinnerId) {
		if (!window.confirm('Cancel this migration and delete staged files?')) {
			return;
		}
		setButtonLoading($btn, spinnerId, true);
		ajax('smig_cancel_migration', { session_id: state.sessionId || '' })
			.done(function (res) {
				if (res.success) {
					resetWizard();
					showNotice(
						'success',
						'Migration cancelled. You can start again.'
					);
				} else {
					showNotice(
						'error',
						res.data && res.data.message
							? res.data.message
							: 'Cancel failed.'
					);
				}
			})
			.fail(function () {
				showNotice('error', 'Cancel request failed.');
			})
			.always(function () {
				setButtonLoading($btn, spinnerId, false);
			});
	}

	function renderApplySummary() {
		var $table = $('#smig-manifest table');
		if ($table.length) {
			$('#smig-apply-summary').html(
				'<div class="notice notice-info inline"><p><strong>Staged content ready to apply</strong></p></div>' +
					$table.clone().wrap('<div/>').parent().html()
			);
		}
	}

	/* Copy buttons */
	$(document).on('click', '.smig-copy', function () {
		var id = $(this).data('target');
		var el = document.getElementById(id);
		if (!el) {
			return;
		}
		var text = el.value !== undefined ? el.value : el.textContent;
		copyToClipboard(String(text).trim());
	});

	function setShareCredentialsInactive(inactive) {
		$('.smig-share-credentials').toggleClass('smig-share-inactive', !!inactive);
	}

	/* Enable / disable pull endpoint on this site */
	$('#smig-endpoint-enabled').on('change', function () {
		var $checkbox = $(this);
		var enabled = $checkbox.is(':checked');
		var previous = !enabled;
		hideNotices();
		setShareCredentialsInactive(!enabled);
		setButtonLoading($checkbox, 'smig-endpoint-spinner', true);
		ajax('smig_save_endpoint', { enabled: enabled ? '1' : '0' })
			.done(function (res) {
				if (res.success) {
					showNotice(
						'success',
						enabled
							? 'Migration endpoint enabled. Share the site URL and auth code with the target site.'
							: 'Migration endpoint disabled. This site cannot be pulled until you enable it again.'
					);
				} else {
					$checkbox.prop('checked', previous);
					setShareCredentialsInactive(previous);
					showNotice(
						'error',
						res.data && res.data.message
							? res.data.message
							: 'Could not update endpoint setting.'
					);
				}
			})
			.fail(function () {
				$checkbox.prop('checked', previous);
				setShareCredentialsInactive(previous);
				showNotice('error', 'Request failed.');
			})
			.always(function () {
				setButtonLoading($checkbox, 'smig-endpoint-spinner', false);
			});
	});

	/* Regenerate auth code */
	$('#smig-regen-btn').on('click', function () {
		if (
			!window.confirm(
				'Regenerate the auth code? Any in-progress pulls using the old code will fail.'
			)
		) {
			return;
		}
		hideNotices();
		var $btn = $(this);
		setButtonLoading($btn, 'smig-regen-spinner', true);
		ajax('smig_regenerate_code')
			.done(function (res) {
				if (res.success && res.data.code) {
					$('#smig-auth-code').val(res.data.code);
					showNotice('success', 'Auth code regenerated.');
				} else {
					showNotice(
						'error',
						res.data && res.data.message
							? res.data.message
							: 'Could not regenerate code.'
					);
				}
			})
			.fail(function () {
				showNotice('error', 'Request failed.');
			})
			.always(function () {
				setButtonLoading($btn, 'smig-regen-spinner', false);
			});
	});

	/* Step 1: Verify */
	$('#smig-verify-btn').on('click', function () {
		hideNotices();
		var url = $('#smig-src-url').val().trim().replace(/\/+$/, '');
		// Strip REST path if the API endpoint was pasted into the site URL field.
		var wpJson = url.indexOf('/wp-json');
		if (wpJson !== -1) {
			url = url.substring(0, wpJson).replace(/\/+$/, '');
			$('#smig-src-url').val(url);
		}
		var auth = $('#smig-src-auth').val().trim();

		if (!url || !auth) {
			showNotice('error', 'Enter both the source URL and auth code.');
			return;
		}

		state.sourceUrl = url;
		state.sourceAuth = auth;

		var $btn = $(this);
		setButtonLoading($btn, 'smig-verify-spinner', true);
		$('#smig-verify-result').empty();

		ajax('smig_verify_source', {
			source_url: url,
			source_auth: auth,
		})
			.done(function (res) {
				if (!res.success) {
					showNotice(
						'error',
						res.data && res.data.message
							? res.data.message
							: 'Verification failed.'
					);
					return;
				}

				var d = res.data;
				state.verified = true;

				var ms = d.is_multisite
					? ' Multisite, blog ID ' +
						escapeHtml(String(d.blog_id)) +
						'.'
					: '';

				$('#smig-verify-result').html(
					'<div class="notice notice-success inline"><p><strong>Connected to ' +
						escapeHtml(d.site_name || 'source site') +
						'</strong><br>' +
						escapeHtml(d.site_url || url) +
						'<br>WordPress ' +
						escapeHtml(d.wp_version || '') +
						ms +
						'</p></div>'
				);
				setStep(2);
			})
			.fail(function (xhr) {
				var msg = 'Could not reach the source site.';
				try {
					var j = JSON.parse(xhr.responseText);
					if (j.data && j.data.message) {
						msg = j.data.message;
					}
				} catch (e) {
					/* ignore */
				}
				showNotice('error', msg);
			})
			.always(function () {
				setButtonLoading($btn, 'smig-verify-spinner', false);
			});
	});

	/* Step 2: Download */
	$('#smig-download-btn').on('click', function () {
		if (!state.verified) {
			showNotice('error', 'Connect to the source site first.');
			return;
		}
		hideNotices();

		var $btn = $(this);
		setButtonLoading($btn, 'smig-dl-spinner', true);
		$('#smig-dl-progress').prop('hidden', false);
		$('#smig-dl-bar').val(0);
		$('#smig-dl-text').text('Fetching manifest…');

		ajax('smig_start_download', {
			source_url: state.sourceUrl,
			source_auth: state.sourceAuth,
			wporg_install: $('#smig-opt-wporg').is(':checked') ? '1' : '0',
			skip_same_version: $('#smig-opt-skip-same').is(':checked')
				? '1'
				: '0',
		})
			.done(function (res) {
				if (!res.success) {
					showNotice(
						'error',
						res.data && res.data.message
							? res.data.message
							: 'Download failed to start.'
					);
					setButtonLoading($btn, 'smig-dl-spinner', false);
					return;
				}

				state.sessionId = res.data.session_id;
				renderManifest(res.data.manifest, {
					files: res.data.files,
					wporg_count: res.data.wporg_count,
					skipped_count: res.data.skipped_count,
					plugin_files_removed: res.data.plugin_files_removed,
				});
				setDownloadRunning(true);
				setMigrationActive(true);

				runDownloadChunks();
			})
			.fail(function () {
				showNotice('error', 'Download request failed.');
				setButtonLoading($btn, 'smig-dl-spinner', false);
			});
	});

	function runDownloadChunks() {
		if (!state.sessionId) {
			return;
		}

		ajax('smig_download_chunk', { session_id: state.sessionId })
			.done(function (res) {
				if (!res.success) {
					showNotice(
						'error',
						res.data && res.data.message
							? res.data.message
							: 'Download chunk failed.'
					);
					setSpinner('smig-dl-spinner', false);
					return;
				}

				var d = res.data;
				$('#smig-dl-bar').val(d.progress);
				$('#smig-dl-text').text(
					d.progress +
						'% — ' +
						(d.current || '') +
						' (' +
						d.done +
						'/' +
						d.total +
						')'
				);

				if (d.phase === 'done') {
					state.downloadComplete = true;
					$('#smig-dl-text').text('Download complete.');
					$('#smig-dl-bar').val(100);
					setSpinner('smig-dl-spinner', false);
					setDownloadRunning(false);
					renderApplySummary();
					setStep(3);
					setMigrationActive(true);
					return;
				}

				setTimeout(runDownloadChunks, 50);
			})
			.fail(function () {
				showNotice('error', 'Download interrupted. Try again.');
				setSpinner('smig-dl-spinner', false);
			});
	}

	/* Step 3: Apply */
	$('#smig-apply-btn').on('click', function () {
		if (!state.sessionId || !state.downloadComplete) {
			showNotice('error', 'Complete the download first.');
			return;
		}

		if (
			!window.confirm(
				'This will replace all content on this site with the downloaded data.\n\nThere is no undo. Continue?'
			)
		) {
			return;
		}

		hideNotices();
		var $btn = $(this);
		setButtonLoading($btn, 'smig-apply-spinner', true);
		$('#smig-apply-progress').prop('hidden', false);
		$('#smig-apply-bar').val(0);
		$('#smig-apply-text').text('Applying…');
		$('#smig-complete').prop('hidden', true);

		setApplyRunning(true);
		setMigrationActive(true);
		runApplyChunks($btn);
	});

	$('#smig-cancel-btn, #smig-cancel-apply-btn').on('click', function () {
		var spinner =
			$(this).attr('id') === 'smig-cancel-apply-btn'
				? 'smig-cancel-apply-spinner'
				: 'smig-cancel-spinner';
		cancelMigration($(this), spinner);
	});

	function runApplyChunks($btn) {
		ajax('smig_apply_chunk', {
			session_id: state.sessionId,
			reset_admin_user: $('#smig-opt-reset-admin').is(':checked') ? '1' : '0',
		})
			.done(function (res) {
				if (!res.success) {
					showNotice(
						'error',
						res.data && res.data.message
							? res.data.message
							: 'Apply failed.'
					);
					setButtonLoading($btn, 'smig-apply-spinner', false);
					return;
				}

				var d = res.data;
				$('#smig-apply-bar').val(d.progress);
				$('#smig-apply-text').text(
					d.progress +
						'% — ' +
						(d.current || '') +
						' (' +
						d.done +
						'/' +
						d.total +
						')'
				);

				if (d.phase === 'done') {
					$('#smig-apply-bar').val(100);
					$('#smig-apply-text').text('Done.');
					$('#smig-complete').prop('hidden', false);
					$('#smig-complete-login-hint').prop(
						'hidden',
						!d.admin_reset
					);
					setApplyRunning(false);
					setMigrationActive(false);
					setButtonLoading($btn, 'smig-apply-spinner', false);
					showNotice(
						'success',
						d.admin_reset
							? 'Migration complete. Sign in as admin / password.'
							: 'Migration complete.'
					);
					return;
				}

				setTimeout(function () {
					runApplyChunks($btn);
				}, 50);
			})
			.fail(function () {
				showNotice('error', 'Apply interrupted.');
				setButtonLoading($btn, 'smig-apply-spinner', false);
			});
	}

	function initFromResume() {
		if (!smig.resume) {
			return;
		}

		var r = smig.resume;
		state.sourceUrl = r.source_url || '';
		state.sessionId = r.session_id || '';
		state.verified = true;
		state.downloadComplete = !!r.download_complete;

		$('#smig-src-url').val(state.sourceUrl);
		$('#smig-src-auth').val(state.sourceAuth);

		if (r.expired) {
			showNotice(
				'warning',
				'The download session expired, but staged files remain. Use Cancel and restart to begin again.'
			);
			setStep(2);
			setMigrationActive(true);
			return;
		}

		showNotice('info', 'Resuming migration where you left off.');

		if (r.manifest) {
			renderManifest(r.manifest, r.manifest_stats || {});
		}

		if (r.resume_apply || (r.apply_started && r.download_complete)) {
			setStep(3);
			renderApplySummary();
			$('#smig-apply-progress').prop('hidden', false);
			$('#smig-apply-bar').val(r.progress || 0);
			$('#smig-apply-text').text(
				(r.progress || 0) + '% — ' + (r.current || 'Resuming apply…')
			);
			setApplyRunning(true);
			setMigrationActive(true);
			if (r.resume_apply) {
				runApplyChunks($('#smig-apply-btn'));
			}
		} else if (r.resume_download) {
			setStep(2);
			$('#smig-dl-progress').prop('hidden', false);
			$('#smig-dl-bar').val(r.progress || 0);
			$('#smig-dl-text').text(
				(r.progress || 0) + '% — ' + (r.current || 'Resuming download…')
			);
			setDownloadRunning(true);
			setMigrationActive(true);
			runDownloadChunks();
		} else if (r.download_complete) {
			setStep(3);
			renderApplySummary();
			setMigrationActive(true);
		}
	}

	setupBeforeUnload();
	initFromResume();

	/* Step nav: view only completed steps */
	$('#smig-step-nav').on('click', '.nav-tab', function (e) {
		e.preventDefault();
		var step = parseInt($(this).data('step'), 10);
		if (step === 1) {
			setStep(1);
		} else if (step === 2 && state.verified) {
			setStep(2);
		} else if (step === 3 && state.downloadComplete) {
			setStep(3);
		}
	});
})(jQuery);
