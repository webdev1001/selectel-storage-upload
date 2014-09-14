<?php
/**
 * Plugin Name: Selectel Storage Upload
 * Plugin URI: http://wm-talk.net/supload-wordpress-plagin-dlya-zagruzki-na-selectel
 * Description: The plugin allows you to upload files from the library to Selectel Storage
 * Version: 1.0.2
 * Author: Mauhem
 * Author URI: http://wm-talk.net/
 * License: GNU GPLv2
 * Text Domain: supload
 * Domain Path: /lang

 */
load_plugin_textdomain('supload', false, dirname(plugin_basename(__FILE__)) . '/lang');

require_once dirname(__FILE__) . '/selectel.class.php';

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
        ), null, true);
        $container = $sel->getContainer(get_option('selupload_container'));
        supload_showMessage(__('Connection is successfully established.', 'supload'));
    } catch (Exception $e) {
        echo($e->getMessage());
    }
}

function supload_isDirEmpty($dir)
{
    if (!is_readable($dir)) {
        return null;
    }
    return (count(scandir($dir)) == 2);
}

function supload_cloudUpload()
{
    try {
        if (!supload_isDirEmpty(get_option('upload_path'))) {
            $name = time() . '_selupload.tar';
            $link = sys_get_temp_dir() . '/' . $name;
            $archive = new PharData ($link);
            $archive->buildFromDirectory(get_option('upload_path') . '/');
            if (file_exists($link)) {
                $archive->compress(Phar::BZ2);
                unset ($archive);
                unlink($link);
            } else {
                throw new Exception (__('TAR archive not exists.', 'supload'));
            }
            if (file_exists($link . '.bz2')) {
                $sel = new supload_SelectelStorage (get_option('selupload_username'), get_option(
                    'selupload_pass'
                ), get_option('selupload_auth'));
                $container = $sel->getContainer(get_option('selupload_container'));
                if ($container->putFile($link . '.bz2', '', 'tar.bz2')) {
                    if ((get_option('selupload_sync') == 'onlystorage')) {
                        supload_delFolder(get_option('upload_path') . '/');
                        return true;
                    }
                    return false;
                }
                return false;
            } else {
                throw new Exception (__('TAR.BZ2 archive not exists.', 'supload'));
            }
        }
        return true;
    } catch (Exception $e) {
        supload_showMessage($e->getCode() . ' :: ' . $e->getMessage());
    }
    return false;
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

add_action(
    'admin_menu',
    function () {
        add_options_page(
            'Selectel Upload',
            'Selectel Upload',
            8,
            __FILE__,
            function () {
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
                                if (isset ($_POST['archive'])) {
                                    supload_cloudUpload();
                                }
                                // Определение настроек по умолчанию
                                if (get_option('upload_path') == 'wp-content/uploads' || get_option(
                                        'upload_path'
                                    ) == null
                                ) {
                                    update_option('upload_path', WP_CONTENT_DIR . '/uploads');
                                }
                                if (get_option('selupload_auth') == null) {
                                    update_option('selupload_auth', 'auth.selcdn.ru');
                                }
                                ?>

                                <form method="post" action="options.php">
                                    <?php wp_nonce_field('update-options'); ?>
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
                                                <td><label for="upload_path"><b><?php _e('Local path', 'supload'); ?>
                                                            :</b></label></td>
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
                                        <input type="hidden" name="page_options"
                                               value="selupload_auth,upload_path,selupload_container,selupload_pass,selupload_username,selupload_sync,upload_url_path,selupload_del"/>
                                        <?php submit_button(); ?>
                                    </fieldset>
                                </form>
                                <form method="post">
                                    <input type="submit" name="test" id="submit" class="button button-primary"
                                           value="<?php _e('Check the connection', 'supload'); ?>"/>
                                    <input type="submit" name="archive" id="submit" class="button button-primary"
                                           value="<?php _e('Manually synchronize', 'supload'); ?>"/>
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
        );
    }
);

function supload_cloudDelete($file)
{
    $sel = new supload_SelectelStorage (get_option('selupload_username'), get_option('selupload_pass'), get_option(
        'selupload_auth'
    ));
    $container = $sel->getContainer(get_option('selupload_container'));
    // Поиск всех миниатюр для удаления вместе с оригиналом
    $files = glob(substr_replace($file, '-*.' . pathinfo($file, PATHINFO_EXTENSION), strripos($file, '.')));
    $files[] = $file;
    foreach ($files as $name) {
        if (!empty ($name)) {
            $del = str_replace(get_option('upload_path'), '', $name);
            //Удаление файлов из контейнера
            $container->delete($del);
            //Удаление из локальной паки
            @unlink($name);
        }
    }
    $tmp = implode(
        ' =|= ',
        glob(substr_replace($file, '-*.' . pathinfo($file, PATHINFO_EXTENSION), strripos($file, '.')))
    );
    file_put_contents('e:\OpenServer\file.txt', $tmp . "\n", FILE_APPEND);
    return $file;
}

//add_action ('add_attachment', 'cloud_upload', 100);
if (get_option('selupload_del') == 1) {
    add_filter('wp_generate_attachment_metadata', 'supload_cloudUpload');
}
add_filter('wp_delete_file', 'supload_cloudDelete', 10, 1);
