<?php
/**
 * Admin settings template for Door Estimator app
 * 
 * @var array $_ Template parameters
 * @var \OCP\IL10N $l10n Localization object
 */

use OCP\Util;

// Add required scripts and styles
Util::addScript('door_estimator', 'admin-settings');
Util::addStyle('door_estimator', 'admin-settings');

$l = $_['l10n'] ?? \OC::$server->getL10N('door_estimator');
?>

<div id="door-estimator-admin" class="section">
    <h2><?php p($l->t('Door Estimator')); ?></h2>
    
    <?php if (isset($_['error'])): ?>
        <div class="msg error">
            <p><?php p($_['error']); ?></p>
        </div>
    <?php else: ?>
        
        <!-- System Status -->
        <div class="admin-section">
            <h3><?php p($l->t('System Status')); ?></h3>
            
            <div class="status-grid">
                <div class="status-item">
                    <label><?php p($l->t('App Version')); ?>:</label>
                    <span><?php p($_['system_info']['app_version'] ?? 'Unknown'); ?></span>
                </div>
                
                <div class="status-item">
                    <label><?php p($l->t('Installation Status')); ?>:</label>
                    <span class="status-<?php p($_['installation_status']['status'] ?? 'unknown'); ?>">
                        <?php p(ucfirst($_['installation_status']['status'] ?? 'Unknown')); ?>
                    </span>
                </div>
                
                <div class="status-item">
                    <label><?php p($l->t('Health Status')); ?>:</label>
                    <span class="status-<?php p($_['health_check']['status'] ?? 'unknown'); ?>">
                        <?php p(ucfirst($_['health_check']['status'] ?? 'Unknown')); ?>
                    </span>
                </div>
                
                <div class="status-item">
                    <label><?php p($l->t('Maintenance Mode')); ?>:</label>
                    <span><?php p($_['system_info']['maintenance_mode'] ? $l->t('Enabled') : $l->t('Disabled')); ?></span>
                </div>
            </div>
            
            <button id="run-health-check" class="button">
                <?php p($l->t('Run Health Check')); ?>
            </button>
        </div>
        
        <!-- Markup Configuration -->
        <div class="admin-section">
            <h3><?php p($l->t('Default Markup Percentages')); ?></h3>
            <p class="settings-hint"><?php p($l->t('These are the default markup percentages applied to new quotes. Users can override these on a per-quote basis.')); ?></p>
            
            <form id="markup-form" class="markup-grid">
                <?php
                $markupLabels = [
                    'doors' => $l->t('Doors'),
                    'doorOptions' => $l->t('Door Options'),
                    'inserts' => $l->t('Inserts'),
                    'frames' => $l->t('Frames'),
                    'frameOptions' => $l->t('Frame Options'),
                    'hinges' => $l->t('Hinges'),
                    'weatherstrip' => $l->t('Weatherstrip'),
                    'closers' => $l->t('Closers'),
                    'locksets' => $l->t('Locksets'),
                    'exitDevices' => $l->t('Exit Devices'),
                    'hardware' => $l->t('Hardware'),
                ];
                
                foreach ($markupLabels as $key => $label):
                    $value = $_['markup_defaults'][$key] ?? 0;
                ?>
                    <div class="markup-item">
                        <label for="markup-<?php p($key); ?>"><?php p($label); ?>:</label>
                        <input type="number" 
                               id="markup-<?php p($key); ?>" 
                               name="<?php p($key); ?>" 
                               value="<?php p($value); ?>" 
                               min="0" 
                               max="100" 
                               step="0.1" 
                               class="markup-input">
                        <span class="markup-unit">%</span>
                    </div>
                <?php endforeach; ?>
                
                <div class="markup-actions">
                    <button type="submit" class="button primary">
                        <?php p($l->t('Save Markup Settings')); ?>
                    </button>
                    <button type="button" id="reset-markups" class="button">
                        <?php p($l->t('Reset to Defaults')); ?>
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Performance Settings -->
        <div class="admin-section">
            <h3><?php p($l->t('Performance Settings')); ?></h3>
            
            <form id="performance-form">
                <div class="setting-item">
                    <label for="cache-timeout"><?php p($l->t('Cache Timeout (seconds)')); ?>:</label>
                    <input type="number" 
                           id="cache-timeout" 
                           name="cache_timeout" 
                           value="<?php p($_['cache_timeout'] ?? 3600); ?>" 
                           min="60" 
                           max="86400">
                    <p class="setting-hint"><?php p($l->t('How long to cache pricing data and configuration (60-86400 seconds)')); ?></p>
                </div>
                
                <div class="setting-item">
                    <label for="pdf-timeout"><?php p($l->t('PDF Generation Timeout (seconds)')); ?>:</label>
                    <input type="number" 
                           id="pdf-timeout" 
                           name="pdf_generation_timeout" 
                           value="<?php p($_['pdf_timeout'] ?? 30); ?>" 
                           min="5" 
                           max="120">
                    <p class="setting-hint"><?php p($l->t('Maximum time allowed for PDF generation (5-120 seconds)')); ?></p>
                </div>
                
                <div class="setting-item">
                    <label for="max-file-size"><?php p($l->t('Max Import File Size (bytes)')); ?>:</label>
                    <input type="number" 
                           id="max-file-size" 
                           name="max_import_file_size" 
                           value="<?php p($_['max_file_size'] ?? 5242880); ?>" 
                           min="1048576" 
                           max="52428800">
                    <p class="setting-hint"><?php p($l->t('Maximum size for data import files (1MB-50MB)')); ?></p>
                </div>
                
                <button type="submit" class="button primary">
                    <?php p($l->t('Save Performance Settings')); ?>
                </button>
            </form>
        </div>
        
        <!-- Feature Flags -->
        <div class="admin-section">
            <h3><?php p($l->t('Feature Settings')); ?></h3>
            
            <form id="features-form">
                <?php
                $featureLabels = [
                    'pdf_generation' => $l->t('PDF Generation'),
                    'data_import' => $l->t('Data Import'),
                    'data_export' => $l->t('Data Export'),
                    'quote_sharing' => $l->t('Quote Sharing'),
                ];
                
                foreach ($featureLabels as $key => $label):
                    $enabled = $_['feature_flags'][$key] ?? false;
                ?>
                    <div class="feature-item">
                        <input type="checkbox" 
                               id="feature-<?php p($key); ?>" 
                               name="<?php p($key); ?>" 
                               <?php if ($enabled) p('checked'); ?>>
                        <label for="feature-<?php p($key); ?>"><?php p($label); ?></label>
                    </div>
                <?php endforeach; ?>
                
                <button type="submit" class="button primary">
                    <?php p($l->t('Save Feature Settings')); ?>
                </button>
            </form>
        </div>
        
        <!-- System Monitoring -->
        <div class="admin-section">
            <h3><?php p($l->t('System Monitoring')); ?></h3>
            
            <form id="monitoring-form">
                <div class="setting-item">
                    <input type="checkbox" 
                           id="enable-performance-monitoring" 
                           name="enable_performance_monitoring" 
                           <?php if ($_['health_config']['enable_performance_monitoring'] ?? false) p('checked'); ?>>
                    <label for="enable-performance-monitoring"><?php p($l->t('Enable Performance Monitoring')); ?></label>
                    <p class="setting-hint"><?php p($l->t('Log performance metrics for operations')); ?></p>
                </div>
                
                <div class="setting-item">
                    <input type="checkbox" 
                           id="enable-debug-logging" 
                           name="enable_debug_logging" 
                           <?php if ($_['health_config']['enable_debug_logging'] ?? false) p('checked'); ?>>
                    <label for="enable-debug-logging"><?php p($l->t('Enable Debug Logging')); ?></label>
                    <p class="setting-hint"><?php p($l->t('Enable detailed debug logging (may impact performance)')); ?></p>
                </div>
                
                <div class="setting-item">
                    <input type="checkbox" 
                           id="maintenance-mode" 
                           name="maintenance_mode" 
                           <?php if ($_['health_config']['maintenance_mode'] ?? false) p('checked'); ?>>
                    <label for="maintenance-mode"><?php p($l->t('Maintenance Mode')); ?></label>
                    <p class="setting-hint"><?php p($l->t('Temporarily disable the app for maintenance')); ?></p>
                </div>
                
                <button type="submit" class="button primary">
                    <?php p($l->t('Save Monitoring Settings')); ?>
                </button>
            </form>
        </div>
        
        <!-- Health Check Results -->
        <?php if (isset($_['health_check']['checks'])): ?>
        <div class="admin-section">
            <h3><?php p($l->t('Health Check Results')); ?></h3>
            
            <div class="health-results">
                <?php foreach ($_['health_check']['checks'] as $checkName => $check): ?>
                    <div class="health-check-item status-<?php p($check['status']); ?>">
                        <h4><?php p(ucfirst(str_replace('_', ' ', $checkName))); ?></h4>
                        <p><?php p($check['message']); ?></p>
                        
                        <?php if (isset($check['duration_ms'])): ?>
                            <small><?php p($l->t('Duration: %s ms', [$check['duration_ms']])); ?></small>
                        <?php endif; ?>
                        
                        <?php if (isset($check['errors']) && !empty($check['errors'])): ?>
                            <ul class="error-list">
                                <?php foreach ($check['errors'] as $error): ?>
                                    <li><?php p($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
    <?php endif; ?>
</div>

<style>
.admin-section {
    margin-bottom: 30px;
    padding: 20px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
}

.status-grid, .markup-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin: 15px 0;
}

.status-item, .markup-item, .setting-item, .feature-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.markup-item {
    flex-direction: column;
    align-items: flex-start;
}

.markup-input {
    width: 80px;
}

.markup-unit {
    font-weight: bold;
}

.status-healthy { color: var(--color-success); }
.status-warning { color: var(--color-warning); }
.status-error { color: var(--color-error); }

.settings-hint, .setting-hint {
    color: var(--color-text-lighter);
    font-size: 0.9em;
    margin-top: 5px;
}

.health-results {
    display: grid;
    gap: 15px;
}

.health-check-item {
    padding: 15px;
    border-radius: var(--border-radius);
    border-left: 4px solid;
}

.health-check-item.status-healthy {
    border-left-color: var(--color-success);
    background-color: var(--color-success-background);
}

.health-check-item.status-warning {
    border-left-color: var(--color-warning);
    background-color: var(--color-warning-background);
}

.health-check-item.status-error {
    border-left-color: var(--color-error);
    background-color: var(--color-error-background);
}

.error-list {
    margin-top: 10px;
    padding-left: 20px;
}

.markup-actions {
    grid-column: 1 / -1;
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.button {
    padding: 8px 16px;
    border-radius: var(--border-radius);
    border: 1px solid var(--color-border);
    background: var(--color-main-background);
    cursor: pointer;
}

.button.primary {
    background: var(--color-primary);
    color: var(--color-primary-text);
    border-color: var(--color-primary);
}

.button:hover {
    background: var(--color-background-hover);
}

.button.primary:hover {
    background: var(--color-primary-hover);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle markup form submission
    const markupForm = document.getElementById('markup-form');
    if (markupForm) {
        markupForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(markupForm);
            const markups = {};
            
            for (let [key, value] of formData.entries()) {
                markups[key] = parseFloat(value);
            }
            
            // Save markup settings via AJAX
            fetch(OC.generateUrl('/apps/door_estimator/api/admin/markups'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'requesttoken': OC.requestToken
                },
                body: JSON.stringify({ markups: markups })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    OC.Notification.showTemporary(t('door_estimator', 'Markup settings saved successfully'));
                } else {
                    OC.Notification.showTemporary(t('door_estimator', 'Failed to save markup settings: ' + data.message), { type: 'error' });
                }
            })
            .catch(error => {
                OC.Notification.showTemporary(t('door_estimator', 'Error saving markup settings'), { type: 'error' });
            });
        });
    }
    
    // Handle health check button
    const healthCheckBtn = document.getElementById('run-health-check');
    if (healthCheckBtn) {
        healthCheckBtn.addEventListener('click', function() {
            healthCheckBtn.disabled = true;
            healthCheckBtn.textContent = t('door_estimator', 'Running...');
            
            fetch(OC.generateUrl('/apps/door_estimator/api/admin/health-check'), {
                method: 'POST',
                headers: {
                    'requesttoken': OC.requestToken
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload(); // Reload to show updated health status
                } else {
                    OC.Notification.showTemporary(t('door_estimator', 'Health check failed: ' + data.message), { type: 'error' });
                }
            })
            .catch(error => {
                OC.Notification.showTemporary(t('door_estimator', 'Error running health check'), { type: 'error' });
            })
            .finally(() => {
                healthCheckBtn.disabled = false;
                healthCheckBtn.textContent = t('door_estimator', 'Run Health Check');
            });
        });
    }
});
</script>