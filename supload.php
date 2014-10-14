<?php
/**
 * Plugin Name: Selectel Storage Upload
 * Plugin URI: http://wm-talk.net/supload-wordpress-plagin-dlya-zagruzki-na-selectel
 * Description: The plugin allows you to upload files from the library to Selectel Storage
 * Version: 1.1.0
 * Author: Mauhem
 * Author URI: http://wm-talk.net/
 * License: GNU GPLv2
 * Text Domain: supload
 * Domain Path: /lang

 */
load_plugin_textdomain('supload', false, dirname(plugin_basename(__FILE__)) . '/lang');

function supload_incompatibile($msg)
{
    require_once ABSPATH . DIRECTORY_SEPARATOR . 'wp-admin' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'plugin.php';
    deactivate_plugins(__FILE__);
    wp_die($msg);
}

if (is_admin() && (!defined('DOING_AJAX') || !DOING_AJAX)) {
    if (version_compare(PHP_VERSION, '5.3.1', '<')) {
        supload_incompatibile(
            __(
                'Plugin Selectel Cloud Uploader requires PHP 5.3.1 or higher. The plugin has now disabled itself.',
                'supload'
            )
        );
    } elseif (!function_exists('curl_version')
        || !($curl = curl_version()) || empty($curl['version']) || empty($curl['features'])
        || version_compare($curl['version'], '7.16.2', '<')
    ) {
        supload_incompatibile(
            __('Plugin Selectel Cloud Uploader requires cURL 7.16.2+. The plugin has now disabled itself.', 'supload')
        );
    } elseif (!($curl['features'] & CURL_VERSION_SSL)) {
        supload_incompatibile(
            __(
                'Plugin Selectel Cloud Uploader requires that cURL is compiled with OpenSSL. The plugin has now disabled itself.',
                'supload'
            )
        );
    }
}

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'supload.curl.php';

function supload_showMessage($message, $errormsg = false)
{
    if ($errormsg) {
        echo '<div id="message" class="error">';
    } else {
        echo '<div id="message" class="updated fade">';
    }

    echo "<p><strong>$message</strong></p></div>";
}

function supload_testConnet()
{
    try {
        $sel = new supload_SelectelStorage (get_option('selupload_username'), get_option('selupload_pass'), get_option(
            'selupload_auth'
        ));
        if ($sel->getContainer(get_option('selupload_container')) instanceof supload_SelectelContainer) {
            supload_showMessage(__('Connection is successfully established.', 'supload'));
        } else {
            supload_showMessage(__('Connection is not established.', 'supload'), true);
        }
    } catch (Exception $e) {
        echo($e->getMessage());
    }
}


function supload_getName($file)
{
    $dir = get_option('upload_path');
    $file = str_replace($dir, '', $file);
    $file = str_replace('\\', '/', $file);
    $file = str_replace(' ', '%20', $file);
    $file = ltrim($file, '/');

    return $file;
}

function supload_cloudUpload($postID)
{
    try {
        $sel = new supload_SelectelStorage (get_option('selupload_username'), get_option(
            'selupload_pass'
        ), get_option('selupload_auth'));
        $container = $sel->getContainer(get_option('selupload_container'));
        $file = get_attached_file($postID);
        if (is_readable($file)) {
            if (($container->putFile($file,
                        supload_getName($file)) == true) and (get_option('selupload_sync') == 'onlystorage')
            ) {
                @unlink($file);
            }
        } else {
            return false;
        }

        return true;
    } catch (Exception $e) {
        supload_showMessage($e->getCode() . ' :: ' . $e->getMessage());
    }

    return false;
}

function supload_thumbUpload($metadata)
{
    try {
        $dir = get_option('upload_path') . DIRECTORY_SEPARATOR . dirname($metadata['file']);
        $storage = new supload_SelectelStorage (get_option('selupload_username'), get_option('selupload_pass'),
            get_option('selupload_auth'));
        $container = $storage->getContainer(get_option('selupload_container'));
        foreach ($metadata['sizes'] as $thumb) {
            $path = $dir . DIRECTORY_SEPARATOR . $thumb['file'];
            if (is_readable($path)) {
                if (($container->putFile($path,
                            supload_getName($path)) == true) and (get_option('selupload_sync') == 'onlystorage')
                ) {
                    @unlink($path);
                }
            }
        }

        return $metadata;
    } catch (Exception $e) {
        supload_showMessage($e->getCode() . ' :: ' . $e->getMessage());
    }

    return $metadata;
}

function supload_isDirEmpty($dir)
{
    if (!is_readable($dir)) {
        return null;
    }

    return (count(scandir($dir)) == 2);
}

function supload_delFolder($dir)
{
    $it = new RecursiveDirectoryIterator ($dir);
    $files = new RecursiveIteratorIterator ($it, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {

        if ($file == '.' || $file == '..') {
            continue;
        }
        if (is_dir($file)) {
            if (supload_isDirEmpty($file)) {
                rmdir($file);
            }
        } else {
            unlink($file);
        }
    }
}

function supload_getFilesArr($dir)
{
    $dir = rtrim($dir, '/');
    $listDir = array();
    if ($handle = opendir($dir)) {
        while (false !== ($file = readdir($handle))) {
            if ($file == '.' || $file == '..') {
                Continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_file($path)) {
                $listDir[] = $path;
            } elseif (is_dir($path)) {
                $listDir = array_merge($listDir, supload_getFilesArr($path));
            }
        }
        closedir($handle);

        return $listDir;
    } else {
        return false;
    }
}

function supload_corURI($path)
{
    if (is_array($path)) {
        $count = count($path) - 1;
        for ($i = 0; $i <= $count; $i++) {
            $path[$i] = stripcslashes($path[$i]);
        }
    } elseif (is_string($path)) {
        $path = stripcslashes($path);
    } else {
        return false;
    }

    return $path;
}

function supload_allSynch()
{
    try {
        if (!empty($_POST['files'])) {
            $_POST['files'] = supload_corURI($_POST['files']);
        }
        $error = '';
        $storage = new supload_SelectelStorage (get_option('selupload_username'), get_option('selupload_pass'),
            get_option('selupload_auth'));
        $container = $storage->getContainer(get_option('selupload_container'));
        if ((!empty($_POST['files'])) and (!empty($_POST['count'])) and (count($_POST['files']) >= 1)) {
            if (is_readable($_POST['files'][count($_POST['files']) - 1])) {

                if (($container->putFile($_POST['files'][count($_POST['files']) - 1],
                            supload_getName($_POST['files'][count($_POST['files']) - 1])) == true) and (get_option('selupload_sync') == 'onlystorage')
                ) {
                    @unlink($_POST['files'][count($_POST['files']) - 1]);
                }
            } else {
                $error = __('Do not have access to the file',
                        'supload') . ': ' . $_POST['files'][count($_POST['files']) - 1];
            }
            unset($_POST['files'][count($_POST['files']) - 1]);
            $progress = round(($_POST['count'] - count($_POST['files'])) / $_POST['count'], 3) * 100;
            wp_send_json(array(
                'files' => $_POST['files'],
                'count' => $_POST['count'],
                'progress' => $progress,
                'error' => $error
            ));
        }
        exit();
    } catch (Exception $e) {
        supload_showMessage($e->getCode() . ' :: ' . $e->getMessage());
    }
}

add_action('wp_ajax_allsynch', 'supload_allSynch');

function supload_stylesheetToAdmin()
{
    wp_enqueue_style('supload-progress', plugins_url('css/admin.css', __FILE__));
}

function supload_settingsPage()
{
    ?>
    <div class="wrap">
    <table>
    <tr>
    <td>
    <h2><?php _e('Settings', 'supload'); ?> Selectel Storage</h2>
    <?php
    if (isset ($_POST['test'])) {
        supload_testConnet();
    }
    // Определение настроек по умолчанию
    if (get_option('upload_path') == 'wp-content' . DIRECTORY_SEPARATOR . 'uploads' || get_option(
            'upload_path'
        ) == null
    ) {
        update_option('upload_path', WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'uploads');
    }
    if (get_option('selupload_auth') == null) {
        update_option('selupload_auth', 'auth.selcdn.ru');
    }
    ?>

    <form method="post" action="options.php">
        <?php settings_fields('supload_settings'); ?>
        <fieldset class="options">
            <table class="form-table">
                <tbody>
                <tr>
                    <td colspan="2"><?php _e(
                            'Type the information for access to your bucket.',
                            'supload'
                        ); ?></td>
                </tr>
                <tr>
                    <td><label for="selupload_username"><b><?php _e(
                                    'Username',
                                    'supload'
                                ); ?>:</b></label></td>
                    <td>
                        <input id="selupload_username" name="selupload_username" type="text"
                               size="15" value="<?php echo esc_attr(
                            get_option('selupload_username')
                        ); ?>" class="regular-text code"/>
                    </td>
                </tr>
                <tr>
                    <td><label for="selupload_pass"><b><?php _e('Password', 'supload'); ?>
                                :</b></label></td>
                    <td>
                        <input id="selupload_pass" name="selupload_pass" type="password"
                               size="15"
                               value="<?php echo esc_attr(get_option('selupload_pass')); ?>"
                               class="regular-text code"/>
                    </td>
                </tr>
                <tr>
                    <td><label for="selupload_container"><b><?php _e(
                                    'Bucket',
                                    'supload'
                                ); ?>:</b></label></td>
                    <td>
                        <input id="selupload_container" name="selupload_container"
                               type="text" size="15" value="<?php echo esc_attr(
                            get_option('selupload_container')
                        ); ?>" class="regular-text code"/>
                    </td>
                </tr>
                <tr>
                    <td><label for="upload_path"><b><?php _e('Local path', 'supload'); ?>:</b></label></td>
                    <td>
                        <input id="upload_path" name="upload_path" type="text" size="15"
                               value="<?php echo esc_attr(get_option('upload_path')); ?>"
                               class="regular-text code"/>

                        <p class="description"><?php _e(
                                'Local path to the uploaded files. By default',
                                'supload'
                            ); ?>: <code>wp-content/uploads</code></p>
                    </td>
                </tr>
                <tr>
                    <td><label for="upload_url_path"><b><?php _e(
                                    'Full URL-path to files',
                                    'supload'
                                ); ?>:</b></label></td>
                    <td>
                        <input id="upload_url_path" name="upload_url_path" type="text"
                               size="15" value="<?php echo esc_attr(
                            get_option('upload_url_path')
                        ); ?>" class="regular-text code"/>

                        <p class="description">
                            <?php _e(
                                'Enter the domain or subdomain if store files only in the Selectel Storage',
                                'supload'
                            ); ?>
                            <code>(http://uploads.example.com)</code>, <?php _e(
                                'or full url path, if only used synchronization',
                                'supload'
                            ); ?>
                            <code>(http://example.com/wp-content/uploads)</code></p>
                    </td>
                </tr>
                <tr>
                    <td><label for="selupload_auth"><b><?php _e(
                                    'Authorization server',
                                    'supload'
                                ); ?>:</b></label></td>
                    <td>
                        <input id="selupload_auth" name="selupload_auth" type="text"
                               size="15"
                               value="<?php echo esc_attr(get_option('selupload_auth')); ?>"
                               class="regular-text code"/>

                        <p class="description"><?php _e('By default', 'supload'); ?>: <code>auth.selcdn.ru</code>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td colspan="2"><?php $options = get_option('selupload_sync'); ?>
                        <p><b><?php _e('Synchronization settings', 'supload'); ?>:</b></p>
                        <input id="onlysync" type="radio" name="selupload_sync"
                               value="onlysync" <?php checked('onlysync' == $options); ?> />
                        <label for="onlysync"><?php _e(
                                'Only synchronize files',
                                'supload'
                            ); ?></label><br/>
                        <input id="onlystorage" type="radio" name="selupload_sync"
                               value="onlystorage" <?php checked(
                            'onlystorage' == $options
                        ); ?> />
                        <label for="onlystorage"><?php _e(
                                'Store files only in the Selectel Storage',
                                'supload'
                            ); ?>.</label>
                        <code>(<?php _e(
                                'to attach a domain / subdomain to store and specify the settings',
                                'supload'
                            ); ?>).</code><br/>
                        <input id="selupload_del" type="checkbox" name="selupload_del"
                               value="1" <?php checked(
                            get_option('selupload_del'),
                            1
                        ); ?> />
                        <label for="selupload_del"><?php _e(
                                'Delete files from the Selectel Storage if they are removed from the library',
                                'supload'
                            ); ?>.</label>
                    </td>
                </tr>
                </tbody>
            </table>
            <input type="hidden" name="action" value="update"/>
            <?php submit_button(); ?>
        </fieldset>
    </form>
    <div id="progressBar">
        <div></div>
    </div>
    <div id="synchtext" style="display: none" class="error"></div>
    <script type="text/javascript" language="JavaScript">
        var prbar = jQuery('#progressBar');
        var synchtext = jQuery('#synchtext');
        function progress(percent, $element) {
            prbar.show(0);
            var progressBarWidth = percent * $element.width() / 100;
            var complete = '';
            if (percent == 100) {
                complete = "<?php _e('Complete', 'supload'); ?>&nbsp;";
            }
            $element.find('div').animate({width: progressBarWidth}, 500).html(percent + "%&nbsp;" + complete);
        }
        function nextfile(files, count) {
            var data = {
                files: files,
                count: count,
                action: 'allsynch'
            };
            jQuery.ajax({
                type: 'POST',
                url: ajaxurl,
                data: data,
                success: function (resp) {
                    progress(resp.progress, prbar);
                    if (resp.error !== '') {
                        synchtext.show(0);
                        synchtext.html(synchtext.html() + '<p><strong>' + resp.error + '</strong></p>');
                    }
                    if (resp.files.length !== 0) {
                        nextfile(resp.files, resp.count);
                    } else {
                        progress(100, prbar);
                        prbar.delay(2000).hide(0);
                    }
                },
                dataType: 'json',
                async: true
            });
        }
        <?php
    $files = supload_getFilesArr(get_option('upload_path'));
    echo 'var files_arr = '.json_encode($files).';'."\n".'var files_count = '.count($files).';'."\n";
?>
        function mansynch(files, count) {
            synchtext.html('');
            synchtext.hide(0);
            prbar.show(0);
            progress(0, prbar);
            console.log(files);
            nextfile(files, count);
        }
    </script>
    <form method="post">
        <input type="submit" name="test" id="submit" class="button button-primary"
               value="<?php _e('Check the connection', 'supload'); ?>"/>
        <input type="button" name="archive" id="submit" class="synch button button-primary"
               value="<?php _e('Full synchronization', 'supload'); ?>" onclick="mansynch(files_arr,files_count)"/>
    </form>
    </td>
    <td style="vertical-align: top; text-align: center; padding-top: 10em">
        <p style="text-align: justify; text-indent: 3em;"><?php _e(
                'You can always help the development of plug-in and contribute to the emergence of new functionality.',
                'supload'
            ); ?>
        </p>

        <p style="text-align: justify; text-indent: 3em;"><?php _e(
                'If you have any ideas or suggestions',
                'supload'
            ); ?>, <a href="mailto:me@wm-talk.net"><?php _e(
                    'contact the author',
                    'supload'
                ); ?></a>.
        </p>

        <p style="text-align: justify; text-indent: 3em;"><?php _e(
                'You can always thank the author of financially.',
                'supload'
            ); ?>
        </p>

        <p><strong>PayPal</strong></p>

        <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
            <input type="hidden" name="cmd" value="_s-xclick">
            <input type="hidden" name="hosted_button_id" value="2PSKHUBC5Z986">
            <input type="image" src="<?php echo plugins_url() . '/' . dirname(
                    plugin_basename(__FILE__)
                ); ?>/img/btn_donateCC_LG.gif" border="0" name="submit"
                   alt="PayPal - The safer, easier way to pay online!">
            <img alt="" border="0" src="<?php echo plugins_url() . '/' . dirname(
                    plugin_basename(__FILE__)
                ); ?>/img/pixel.gif" width="1" height="1">
        </form>

        <p><strong><?php _e('Yandex.Money', 'supload'); ?></strong><br/>
            410011704884638
            <br/></p>
        <strong>Webmoney</strong><br/>
        WMZ - Z149988560659<br/>
        WMR - R255107795656
    </td>
    </tr>
    </table>
    </div>
<?php
}

function supload_createMenu()
{
    add_options_page(
        'Selectel Upload',
        'Selectel Upload',
        'manage_options',
        __FILE__,
        'supload_settingsPage'
    );
    add_action('admin_init', 'supload_regsettings');
}

add_action('admin_menu', 'supload_createMenu');

function supload_cloudDelete($file)
{
    $sel = new supload_SelectelStorage (get_option('selupload_username'), get_option('selupload_pass'), get_option(
        'selupload_auth'
    ));
    $container = $sel->getContainer(get_option('selupload_container'));
    $container->delete(supload_getName($file));
    $shab = array();
    if (preg_match("/(.+)-(\d{3,4})x(\d{3,4})\.(.*)/u", $file, $shab)) {
        $files = glob(get_option('upload_path') . DIRECTORY_SEPARATOR . $shab[1] . '-*.' . $shab[4]);
        $files[] = get_option('upload_path') . DIRECTORY_SEPARATOR . $shab[1] . '.' . $shab[4];

    } else {
        $files = glob(get_option('upload_path') . DIRECTORY_SEPARATOR . substr_replace($file,
                '-*.' . pathinfo($file, PATHINFO_EXTENSION), strripos($file, '.')));
        $files[] = $file;
    }
    foreach ($files as $name) {
        if (!empty ($name)) {
            $container->delete(supload_getName($name));
            @unlink($name);
        }
    }

    return $file;
}

if (get_option('selupload_del') == 1) {
    add_filter('wp_delete_file', 'supload_cloudDelete', 10, 1);
}
add_filter('wp_generate_attachment_metadata', 'supload_thumbUpload', 10, 1);
add_action('add_attachment', 'supload_cloudUpload', 10, 1);
add_action('admin_enqueue_scripts', 'supload_stylesheetToAdmin');
function supload_regsettings()
{
    register_setting('supload_settings', 'selupload_auth');
    register_setting('supload_settings', 'upload_path');
    register_setting('supload_settings', 'selupload_container');
    register_setting('supload_settings', 'selupload_pass');
    register_setting('supload_settings', 'selupload_username');
    register_setting('supload_settings', 'selupload_sync');
    register_setting('supload_settings', 'upload_url_path');
    register_setting('supload_settings', 'selupload_del');
}



