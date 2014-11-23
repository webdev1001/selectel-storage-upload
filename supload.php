<?php
/**
 * Plugin Name: Selectel Storage Upload
 * Plugin URI: http://wm-talk.net/supload-wordpress-plagin-dlya-zagruzki-na-selectel
 * Description: The plugin allows you to upload files from the library to Selectel Storage
 * Version: 1.2.3
 * Author: Mauhem
 * Author URI: http://wm-talk.net/
 * License: GNU GPLv2
 * Text Domain: selupload
 * Domain Path: /lang

 */
load_plugin_textdomain('selupload', false, dirname(plugin_basename(__FILE__)) . '/lang');

function selupload_incompatibile($msg)
{
    require_once ABSPATH . DIRECTORY_SEPARATOR . 'wp-admin' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'plugin.php';
    deactivate_plugins(__FILE__);
    wp_die($msg);
}

if (is_admin() && (!defined('DOING_AJAX') || !DOING_AJAX)) {
    if (version_compare(PHP_VERSION, '5.3.3', '<')) {
        selupload_incompatibile(
            __(
                'Plugin Selectel Cloud Uploader requires PHP 5.3.3 or higher. The plugin has now disabled itself.',
                'selupload'
            )
        );
    } elseif (!function_exists('curl_version')
        || !($curl = curl_version()) || empty($curl['version']) || empty($curl['features'])
        || version_compare($curl['version'], '7.16.2', '<')
    ) {
        selupload_incompatibile(
            __('Plugin Selectel Cloud Uploader requires cURL 7.16.2+. The plugin has now disabled itself.', 'selupload')
        );
    } elseif (!($curl['features'] & CURL_VERSION_SSL)) {
        selupload_incompatibile(
            __(
                'Plugin Selectel Cloud Uploader requires that cURL is compiled with OpenSSL. The plugin has now disabled itself.',
                'selupload'
            )
        );
    }
}

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use OpenStackStorage\Connection;

function selupload_showMessage($message, $errormsg = false)
{
    if ($errormsg) {
        echo '<div id="message" class="error">';
    } else {
        echo '<div id="message" class="updated fade">';
    }

    echo "<p><strong>$message</strong></p></div>";
}

function selupload_testConnet()
{
    try {
        isset($_POST['login']) ? $login = $_POST['login'] : $login = get_option('selupload_username');
        isset($_POST['password']) ? $password = $_POST['password'] : $password = get_option('selupload_pass');
        isset($_POST['server']) ? $server = $_POST['server'] : $server = get_option('selupload_auth');
        isset($_POST['container']) ? $container = $_POST['container'] : $container = get_option('selupload_container');

        $connection = new Connection($login, $password, array('authurl' => 'https://' . $server . '/'), 15);
        $connection->getContainer($container);
        selupload_showMessage(__('Connection is successfully established. Save the settings.', 'selupload'));

        exit();
    } catch (Exception $e) {
        selupload_showMessage(__('Connection is not established.',
                'selupload') . ' : ' . $e->getMessage() . ($e->getCode() == 0 ? '' : ' - ' . $e->getCode()), true);
        exit();
    }
}

add_action('wp_ajax_selupload_testConnet', 'selupload_testConnet');

function selupload_getName($file)
{
    $dir = get_option('upload_path');
    $file = str_replace($dir, '', $file);
    $file = str_replace('\\', '/', $file);
    $file = str_replace(' ', '%20', $file);
    $file = ltrim($file, '/');

    return $file;
}

function selupload_cloudUpload($postID)
{
    try {
        $connection = new Connection(get_option('selupload_username'), get_option('selupload_pass'),
            array('authurl' => 'https://' . get_option('selupload_auth') . '/'),15);
        $container = $connection->getContainer(get_option('selupload_container'));
        $file = get_attached_file($postID);
        if (is_readable($file)) {
            $fp = fopen($file, 'r');
            $object = $container->createObject(selupload_getName($file));
            $object->write($fp);
            @fclose($fp);
            $object = $container->getObject(selupload_getName($file));
            if (($object instanceof \OpenStackStorage\Object) and (get_option('selupload_sync') == 'onlystorage')
            ) {
                @unlink($file);
            }
        }

        return true;
    } catch (Exception $e) {
        selupload_showMessage(($e->getCode() != 0 ? $e->getCode() == 0 . ' :: ' : '') . $e->getMessage());
    }

    return false;
}

function selupload_thumbUpload($metadata)
{
    try {
        $connection = new Connection(get_option('selupload_username'), get_option('selupload_pass'),
            array('authurl' => 'https://' . get_option('selupload_auth') . '/'),15);
        $container = $connection->getContainer(get_option('selupload_container'));
        $dir = get_option('upload_path') . DIRECTORY_SEPARATOR . dirname($metadata['file']);
        foreach ($metadata['sizes'] as $thumb) {
            $path = $dir . DIRECTORY_SEPARATOR . $thumb['file'];
            if (is_readable($path)) {
                $fp = fopen($path, 'r');
                $object = $container->createObject(selupload_getName($path));
                $object->write($fp);
                @fclose($fp);
                $object = $container->getObject(selupload_getName($path));
                if (($object instanceof \OpenStackStorage\Object) and (get_option('selupload_sync') == 'onlystorage')) {
                    @unlink($path);
                }
            }
        }

        return $metadata;
    } catch (Exception $e) {
        return $metadata;
        //selupload_showMessage($e->getCode() . ' :: ' . $e->getMessage());
    }
}

function selupload_isDirEmpty($dir)
{
    if (!is_readable($dir)) {
        return null;
    }

    return (count(scandir($dir)) == 2);
}

function selupload_getFilesArr($dir)
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
                $listDir = array_merge($listDir, selupload_getFilesArr($path));
            }
        }
        closedir($handle);

        return $listDir;
    } else {
        return false;
    }
}

function selupload_corURI($path)
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

function selupload_allSynch()
{
    try {
        if (!empty($_POST['files'])) {
            $files = selupload_corURI(explode('||',$_POST['files']));
        }
        $error = '';
        if ((!empty($files)) and (!empty($_POST['count'])) and (count($files) >= 1)) {
        ob_start('ob_gzhandler');
        $connection = new Connection(get_option('selupload_username'), get_option('selupload_pass'),
            array('authurl' => 'https://' . get_option('selupload_auth') . '/'), 20);
        $container = $connection->getContainer(get_option('selupload_container'));

        if ($container instanceof \OpenStackStorage\Container) {
                $thisfile = $files[count($files) - 1];
                $filename = selupload_getName($files[count($files) - 1]);
                if (is_readable($thisfile)) {
                    $fp = fopen($thisfile, 'r');
                    $object = $container->createObject($filename);
                    $object->write($fp);
                    @fclose($fp);
                    $object = $container->getObject($filename);
                    if (($object instanceof \OpenStackStorage\Object) and get_option('selupload_sync') == 'onlystorage') {
                        @unlink($thisfile);
                    } elseif (($object instanceof \OpenStackStorage\Object) !== true) {
                        $error = __('Impossible to upload a file',
                                'selupload') . ': ' . $thisfile;
                    }
                } else {
                    $error = __('Do not have access to the file',
                            'selupload') . ': ' . $thisfile;
                }
                unset($files[count($files) - 1]);
                $progress = round(($_POST['count'] - count($files)) / $_POST['count'], 4) * 100;
                wp_send_json(array(
                    'files' => implode('||',$files),
                    'count' => $_POST['count'],
                    'progress' => $progress,
                    'error' => $error
                ));
            }else {
            $error = __('Unable to connect to the container',
                    'selupload') . ': ' . $_POST['files'][count($_POST['files']) - 1];
            wp_send_json(array(
                'files' => $_POST['files'],
                'count' => $_POST['count'],
                'progress' => 'Error',
                'error' => $error
            ));
            }
        }else{
            $error = __('Data empty',
                'selupload');
            wp_send_json(array(
                'files' => null,
                'count' => null,
                'progress' => 'Error',
                'error' => $error
            ));
        }
        exit();
    } catch (Exception $e) {
        $error = __('Impossible to upload a file',
                'selupload') . ': ' . $_POST['files'][count($_POST['files']) - 1];
        wp_send_json(array(
            'files' => !empty($_POST['files'])?$_POST['files']:null,
            'count' => !empty($_POST['count'])?$_POST['count']:null,
            'progress' => 'Error',
            'error' => $error . ' : ' . ($e->getCode() != 0 ? $e->getCode() . ' :: ' : '') . $e->getMessage()
        ));
        exit();
    }
}

add_action('wp_ajax_selupload_allsynch', 'selupload_allSynch');

function selupload_settingsPage()
{
    ?>
    <div id="selupload_spinner" class="selupload_spinner" style="display:none;">
        <img id="img-spinner" src="<?php echo plugins_url() . '/' . dirname(
                plugin_basename(__FILE__)
            ); ?>/img/loading.gif" alt="Loading"/>
    </div>
    <div class="wrap" id="selupload_wrap">
    <div id="selupload_message" style="display: none"></div>
    <table>
    <tr>
    <td>
    <h2><?php _e('Settings', 'selupload'); ?> Selectel Storage</h2>
    <?php
    // Default settings
    if (get_option('upload_path') == 'wp-content' . DIRECTORY_SEPARATOR . 'uploads' || get_option('upload_path') == null
    ) {
        update_option('upload_path', WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'uploads');
    }
    if (get_option('selupload_auth') == null) {
        update_option('selupload_auth', 'auth.selcdn.ru');
    }
    ?>
    <form method="post" action="options.php">
        <?php settings_fields('selupload_settings'); ?>
        <fieldset class="options">
            <table class="form-table">
                <tbody>
                <tr>
                    <td colspan="2"><?php _e(
                            'Type the information for access to your bucket.',
                            'selupload'
                        );?> <?php _e('No account? <a href ="http://goo.gl/8Z0q8H">Sign up</a>', 'selupload'); ?></td>
                </tr>
                <tr>
                    <td><label for="selupload_username"><b><?php _e(
                                    'Username',
                                    'selupload'
                                ); ?>:</b></label></td>
                    <td>
                        <input id="selupload_username" name="selupload_username" type="text"
                               size="15" value="<?php echo esc_attr(
                            get_option('selupload_username')
                        ); ?>" class="regular-text code"/>
                    </td>
                </tr>
                <tr>
                    <td><label for="selupload_pass"><b><?php _e('Password', 'selupload'); ?>
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
                                    'selupload'
                                ); ?>:</b></label></td>
                    <td>
                        <input id="selupload_container" name="selupload_container"
                               type="text" size="15" value="<?php echo esc_attr(
                            get_option('selupload_container')
                        ); ?>" class="regular-text code"/>
                        <input type="button" name="test" id="submit" class="button button-primary"
                               value="<?php _e('Check the connection', 'selupload'); ?>"
                               onclick="selupload_testConnet()"/>
                    </td>
                </tr>
                <tr>
                    <td><label for="upload_path"><b><?php _e('Local path', 'selupload'); ?>:</b></label></td>
                    <td>
                        <input id="upload_path" name="upload_path" type="text" size="15"
                               value="<?php echo esc_attr(get_option('upload_path')); ?>"
                               class="regular-text code"/>

                        <p class="description"><?php _e(
                                'Local path to the uploaded files. By default',
                                'selupload'
                            ); ?>: <code>wp-content/uploads</code></p>
                    </td>
                </tr>
                <tr>
                    <td><label for="upload_url_path"><b><?php _e(
                                    'Full URL-path to files',
                                    'selupload'
                                ); ?>:</b></label></td>
                    <td>
                        <input id="upload_url_path" name="upload_url_path" type="text"
                               size="15" value="<?php echo esc_attr(
                            get_option('upload_url_path')
                        ); ?>" class="regular-text code"/>

                        <p class="description">
                            <?php _e(
                                'Enter the domain or subdomain if store files only in the Selectel Storage',
                                'selupload'
                            ); ?>
                            <code>(http://uploads.example.com)</code>, <?php _e(
                                'or full url path, if only used synchronization',
                                'selupload'
                            ); ?>
                            <code>(http://example.com/wp-content/uploads)</code></p>
                    </td>
                </tr>
                <tr>
                    <td><label for="selupload_auth"><b><?php _e(
                                    'Authorization server',
                                    'selupload'
                                ); ?>:</b></label></td>
                    <td>
                        <input id="selupload_auth" name="selupload_auth" type="text"
                               size="15"
                               value="<?php echo esc_attr(get_option('selupload_auth')); ?>"
                               class="regular-text code"/>

                        <p class="description"><?php _e('By default', 'selupload'); ?>: <code>auth.selcdn.ru</code>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td colspan="2"><?php $options = get_option('selupload_sync'); ?>
                        <p><b><?php _e('Synchronization settings', 'selupload'); ?>:</b></p>
                        <input id="onlysync" type="radio" name="selupload_sync"
                               value="onlysync" <?php checked('onlysync' == $options); ?> />
                        <label for="onlysync"><?php _e(
                                'Only synchronize files',
                                'selupload'
                            ); ?></label><br/>
                        <input id="onlystorage" type="radio" name="selupload_sync"
                               value="onlystorage" <?php checked(
                            'onlystorage' == $options
                        ); ?> />
                        <label for="onlystorage"><?php _e(
                                'Store files only in the Selectel Storage',
                                'selupload'
                            ); ?>.</label>
                        <code>(<?php _e(
                                'to attach a domain / subdomain to store and specify the settings',
                                'selupload'
                            ); ?>).</code><br/>
                        <input id="selupload_del" type="checkbox" name="selupload_del"
                               value="1" <?php checked(
                            get_option('selupload_del'),
                            1
                        ); ?> />
                        <label for="selupload_del"><?php _e(
                                'Delete files from the Selectel Storage if they are removed from the library',
                                'selupload'
                            ); ?>.</label>
                    </td>
                </tr>
                </tbody>
            </table>
            <input type="hidden" name="action" value="update"/>
            <?php submit_button(); ?>
        </fieldset>
    </form>
    <div id="selupload_progressBar">
        <div></div>
    </div>
    <div id="selupload_synchtext" style="display: none" class="error"></div>
    <script type="text/javascript" language="JavaScript">
        <?php
    $files = selupload_getFilesArr(get_option('upload_path'));
    echo 'var files_arr = '.json_encode(implode('||',$files)).';'."\n".'var files_count = '.count($files).';'."\n";
?>
    </script>
    <form method="post">
        <input type="button" name="archive" id="submit" class="synch button button-primary"
               value="<?php _e('Manual synchronization', 'selupload'); ?>"
               onclick="selupload_mansynch(files_arr,files_count)"/>
    </form>
    </td>
    <td style="vertical-align: top; text-align: center; padding-top: 10em">
        <p style="text-align: justify; text-indent: 3em;"><?php _e(
                'You can always help the development of plug-in and contribute to the emergence of new functionality.',
                'selupload'
            ); ?>
        </p>

        <p style="text-align: justify; text-indent: 3em;"><?php _e(
                'If you have any ideas or suggestions',
                'selupload'
            ); ?>: <a href="mailto:me@wm-talk.net"><?php _e(
                    'contact the author',
                    'selupload'
                ); ?></a>.
        </p>

        <p style="text-align: justify; text-indent: 3em;"><?php _e(
                'You can always thank the author of financially.',
                'selupload'
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

        <p><strong><?php _e('Yandex.Money', 'selupload'); ?></strong><br/>
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

function selupload_createMenu()
{
    add_options_page(
        'Selectel Upload',
        'Selectel Upload',
        'manage_options',
        __FILE__,
        'selupload_settingsPage'
    );
    add_action('admin_init', 'selupload_regsettings');
}

add_action('admin_menu', 'selupload_createMenu');

function selupload_cloudDelete($file)
{
    try {
        $connection = new Connection(get_option('selupload_username'), get_option('selupload_pass'),
            array('authurl' => 'https://' . get_option('selupload_auth') . '/'));
        $container = $connection->getContainer(get_option('selupload_container'));
        $container->deleteObject(selupload_getName($file));
        @unlink(get_option('upload_path') . DIRECTORY_SEPARATOR . selupload_getName($file));

        return $file;
    } catch (Exception $e) {
        return $file;
    }

}

if (get_option('selupload_del') == 1) {
    add_filter('wp_delete_file', 'selupload_cloudDelete', 10, 1);
}

function selupload_scripts()
{
    //wp_deregister_script('jquery');
    //wp_enqueue_script('jquery','https://code.jquery.com/jquery-1.11.1.min.js',false,'1.11.1',false);
    wp_enqueue_script('selupload_js', plugins_url( '/js/script.js' , __FILE__ ), array( 'jquery' ), '1.2.1',true);
}
function selupload_stylesheetToAdmin()
{
    wp_enqueue_style('selupload-progress', plugins_url('css/admin.css', __FILE__));
}

add_filter('wp_generate_attachment_metadata', 'selupload_thumbUpload', 10, 1);
add_action('add_attachment', 'selupload_cloudUpload', 10, 1);
add_action('admin_enqueue_scripts', 'selupload_stylesheetToAdmin');
add_action( 'admin_enqueue_scripts', 'selupload_scripts' );

function selupload_regsettings()
{
    register_setting('selupload_settings', 'selupload_auth');
    register_setting('selupload_settings', 'upload_path');
    register_setting('selupload_settings', 'selupload_container');
    register_setting('selupload_settings', 'selupload_pass');
    register_setting('selupload_settings', 'selupload_username');
    register_setting('selupload_settings', 'selupload_sync');
    register_setting('selupload_settings', 'upload_url_path');
    register_setting('selupload_settings', 'selupload_del');
}



