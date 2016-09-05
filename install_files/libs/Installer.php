<?php

class Installer
{
    private $logFile;
    private $tempDirectory;
    private $baseDirectory;
    private $databaseDirectory;
    private $initEventMessage = false;

    public function __construct()
    {
        $this->baseDirectory = PATH_INSTALL;
        $this->logFile = $this->baseDirectory.'/install_files/install.log';
        $this->tempDirectory = $this->baseDirectory.'/install_files/temp';
        $this->databaseDirectory = $this->baseDirectory.DIRECTORY_SEPARATOR.'database';

        if (!is_null($handler = $this->post('handler'))) {
            if (!strlen($handler)) {
                exit;
            }

            try {
                if (method_exists($this, $handler)) {
                    if (($result = $this->$handler()) !== null) {
                        $this->log('Execute handler (%s): %s', $handler, print_r($result, true));
                        header('Content-Type: application/json');
                        die(json_encode($result));
                    }
                } else {
                    throw new Exception(sprintf('Invalid handler: %s', $handler));
                }
            } catch (Exception $ex) {
                $this->log('Handler error (%s): %s', $handler, $ex->getMessage());
                $this->log(['Trace log:', '%s'], $ex->getTraceAsString());
                header($_SERVER['SERVER_PROTOCOL'].' 520 Internal Server Error', true, 520);
                die($ex->getMessage());
            }

            exit;
        }
    }

    public function checkSystem()
    {
        $codes = [
            'liveConnection', 'writePermission',
            'phpVersion', 'pdoLibrary', 'mcryptLibrary',
            'mbstringLibrary', 'sslLibrary', 'gdLibrary',
            'curlLibrary', 'zipLibrary', 'procOpen', 'symbolicLink',
        ];

        $status = false;

        if (in_array($code = $this->post('code'), $codes)) {
            if (!$this->checkCode($code)) {
                throw new InstallerException('Fail !', $code);
            }
        }
    }

    public function checkDatabase()
    {
        if ($this->post('db_type') != 'sqlite') {
            if (!strlen($this->post('db_host'))) {
                throw new InstallerException('Please specify a database host', 'db_host');
            }

            if (!strlen($this->post('db_username'))) {
                throw new InstallerException('Please specify a database username', 'db_username');
            }

            if (!strlen($this->post('db_password'))) {
                throw new InstallerException('Please specify a database password', 'db_password');
            }
        }

        if (!strlen($this->post('db_name'))) {
            throw new InstallerException('Please specify the database name', 'db_name');
        }

        $config = [
            'type' => $this->post('db_type'),
            'host' => $this->post('db_host'),
            'port' => $this->post('db_port'),
            'name' => $this->post('db_name'),
            'user' => $this->post('db_username'),
            'pass' => $this->post('db_password'),
        ];

        extract($config);

        switch ($type) {
            case 'mysql':
                $dsn = 'mysql:host='.$host.';dbname='.$name;
                if ($port) {
                    $dsn .= ';port='.$port;
                }

                break;

            case 'postgresql':
                $_host = ($host) ? 'host='.$host.';' : '';
                $dsn = 'pgsql:'.$_host.'dbname='.$name;
                if ($port) {
                    $dsn .= ';port='.$port;
                }

                break;

            case 'sqlite':
                $dsn = 'sqlite:'.$this->databaseDirectory.DIRECTORY_SEPARATOR.$name;
                $this->validateSqliteFile($this->databaseDirectory.DIRECTORY_SEPARATOR.$name);
                break;

            case 'sqlserver':
                $availableDrivers = PDO::getAvailableDrivers();
                $_port = $port ? ','.$port : '';
                if (in_array('dblib', $availableDrivers)) {
                    $dsn = 'dblib:host='.$host.$_port.';dbname='.$name;
                } else {
                    $dsn = 'sqlsrv:Server='.$host.(empty($port) ? '' : ','.$_port).';Database='.$name;
                }
                break;
        }
        try {
            $db = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (PDOException $ex) {
            throw new Exception('Connection failed: '.$ex->getMessage());
        }
    }

    public function checkAdmin()
    {
        if (!strlen($this->post('admin_name'))) {
            throw new InstallerException('Please specify administrator name', 'admin_name');
        }

        if (!strlen($this->post('admin_email'))) {
            throw new InstallerException('Please specify administrator email address', 'admin_email');
        }

        if (!filter_var($this->post('admin_email'), FILTER_VALIDATE_EMAIL)) {
            throw new InstallerException('Please specify valid email address', 'admin_email');
        }

        if (!strlen($this->post('admin_password'))) {
            throw new InstallerException('Please specify password', 'admin_password');
        }

        if (strlen($this->post('admin_password')) < 4) {
            throw new InstallerException('Please specify password length more than 4 characters', 'admin_password');
        }

        if (strlen($this->post('admin_password')) > 255) {
            throw new InstallerException('Please specify password length less than 64 characters', 'admin_password');
        }

        if (!strlen($this->post('admin_password_confirmation'))) {
            throw new InstallerException('Please confirm chosen password', 'admin_password_confirmation');
        }

        if (strcmp($this->post('admin_password'), $this->post('admin_password_confirmation'))) {
            throw new InstallerException('Specified password does not match the confirmed password', 'admin_password');
        }
    }

    public function checkSettings()
    {
        if (!strlen($this->post('website_name'))) {
            throw new InstallerException('Please specify your website name', 'website_name');
        }

        if (!strlen($this->post('website_description'))) {
            throw new InstallerException('Please specify your website description', 'website_description');
        }
    }

    /*
    Install
     */

    public function downloadLatestVersion()
    {
        if ($this->stepCompleted('downloadLatestVersion')) {
            $this->sendEventMessage('complete', 'Process complete');

            return;
        }

        $content = json_decode(file_get_contents(ZEDx_GATEWAY));
        $uri = $content->archive;
        $hash = $content->checksum;

        $this->requestServerFile('zedx-core', $hash, $uri, ['type' => 'install']);

        $this->completeStep('downloadLatestVersion');
    }

    public function extractCore()
    {
        if ($this->stepCompleted('extractCore')) {
            $this->sendEventMessage('complete', 'Process complete');

            return;
        }

        $this->log('Extracting ZEDx Core');
        $this->sendEventMessage('progress', 'Extracting ZEDx Core', 1);

        if (!$this->unzipFile('zedx-core')) {
            $this->log('Extracting ZEDx Core [ FAIL ]');
            throw new InstallerException("Can't extract ZEDx Core", 'zedx-core');
        }

        $this->sendEventMessage('progress', 'Disabling Htaccess', 90);
        $this->disableHtaccess();

        $this->log('Extracting ZEDx Core [OK]');
        $this->sendEventMessage('complete', 'Process complete');

        $this->completeStep('extractCore');
    }

    public function changePermissions()
    {
        if ($this->stepCompleted('changePermissions')) {
            $this->sendEventMessage('complete', 'Process complete');

            return;
        }

        $this->bootZEDx();
        $this->log('Changing permissions');
        $this->sendEventMessage('progress', 'Changing permissions', 1);

        rchmod(storage_path(), 0777, 0777);
        rchmod(base_path('bootstrap/cache'), 0777, 0777);

        if ($this->post('db_type') == 'sqlite') {
            $db_name = $this->post('db_name', '');
            rchmod($this->databaseDirectory.DIRECTORY_SEPARATOR.$db_name, 0777, 0777);
        }

        $this->log('Changing permissions [OK]');
        $this->sendEventMessage('complete', 'Process complete');

        $this->completeStep('changePermissions');
    }

    public function buildConfigs()
    {
        if ($this->stepCompleted('buildConfigs')) {
            $this->sendEventMessage('complete', 'Process complete');

            return;
        }

        $this->bootZEDx();
        $this->log('Building Configs');
        $this->sendEventMessage('progress', 'Building Configs', 1);

        if (!File::exists(base_path('.env'))) {
            File::copy(base_path('.env.example'), base_path('.env'));
        }

        $this->rewriteEnv([
            'MAIL_FROM_ADDRESS'  => $this->post('admin_email', 'mailer@example.com'),
            'MAIL_FROM_NAME'     => $this->post('website_name', 'ZEDx'),
            'APP_URL'            => $this->getBaseUrl(),
            'APP_LOCALE'         => 'fr',
            'APP_KEY'            => str_random(32),
            'APP_FRONTEND_THEME' => 'Default',
        ]);

        $this->rewriteEnv($this->getDatabaseConfigValues());

        $this->log('Building Configs [ OK ]');
        $this->log('default database : %s', config('database.default'));

        $this->rewriteEnv([
            'APP_ENV' => 'local',
        ]);

        $this->sendEventMessage('complete', 'Process complete');

        $this->completeStep('buildConfigs');
    }

    public function migrateDatabase()
    {
        if ($this->stepCompleted('migrateDatabase')) {
            $this->sendEventMessage('complete', 'Process complete');

            return;
        }

        $this->bootZEDx();

        $this->log('Starting Database Migration');
        $this->sendEventMessage('progress', 'Starting Database Migration', 1);

        $this->artisan('migrate', ['--seed' => true, '--force' => true]);
        $this->sendEventMessage('complete', 'Process complete');

        $this->completeStep('migrateDatabase');
    }

    private function artisan($command, $params = [])
    {
        $this->log('Starting Artisan command %s', $command);
        Artisan::call($command, $params);
        $this->log('Artisan response');
        $this->log(Artisan::output());
    }

    public function createAdminAccount()
    {
        if ($this->stepCompleted('createAdminAccount')) {
            $this->sendEventMessage('complete', 'Process complete');

            return;
        }

        $this->bootZEDx();

        $this->log('Creating Admin Account');
        $this->sendEventMessage('progress', 'Creating Administration Area', 1);

        $admin = \ZEDx\Models\Admin::firstOrFail();
        $admin->name = $this->post('admin_name', 'Administrator');
        $admin->email = $this->post('admin_email', 'admin@example.com');
        $admin->password = $this->post('admin_password', str_random(10));
        $admin->save();

        $this->sendEventMessage('complete', 'Process complete');

        $this->completeStep('createAdminAccount');
    }

    public function createSetting()
    {
        if ($this->stepCompleted('createSetting')) {
            $this->sendEventMessage('complete', 'Process complete');

            return;
        }

        $this->bootZEDx();

        $this->log('Creating Setting');
        $this->sendEventMessage('progress', 'Creating Setting', 1);

        $setting = \ZEDx\Models\Setting::firstOrFail();

        $setting->website_name = $this->post('website_name', 'ZEDx');
        $setting->website_url = $this->getBaseUrl();
        $setting->website_title = $this->post('website_title', 'ZEDx');
        $setting->website_description = $this->post('website_description', 'Classifieds CMS');
        $setting->save();
        $this->sendEventMessage('complete', 'Process complete');

        $this->completeStep('createSetting');
    }

    public function setDefaultTheme()
    {
        if ($this->stepCompleted('setDefaultTheme')) {
            $this->sendEventMessage('complete', 'Process complete');

            return;
        }

        $this->bootZEDx();

        $this->sendEventMessage('progress', 'Setting Default Theme', 1);

        $this->log('Publishing Backend Assets');
        $this->artisan('backend:publish', ['--force' => true]);

        $this->log('Setting Default Theme');
        \Themes::frontend()->setActive('Default');

        // Publish Widgets Assets
        $this->log('Publishing Widgets Assets');
        $this->artisan('widget:publish', ['--force' => true]);

        $this->sendEventMessage('complete', 'Process complete');

        $this->completeStep('setDefaultTheme');
    }

    public function createSymLinks()
    {
        if ($this->stepCompleted('createSymLinks')) {
            $this->sendEventMessage('complete', 'Process complete');

            return;
        }

        $this->bootZEDx();

        $this->log('Creating symbolic links');
        $this->sendEventMessage('progress', 'Creating symbolic links', 1);

        symlink(storage_path('app/uploads'), public_path('uploads'));
        $this->sendEventMessage('complete', 'Process complete');

        $this->completeStep('createSymLinks');
    }

    public function clearAll()
    {
        if ($this->stepCompleted('clearAll')) {
            $this->sendEventMessage('complete', 'Process complete');

            return;
        }

        $this->bootZEDx();

        $this->log('Clean All');
        $this->sendEventMessage('progress', 'Clean installation files', 1);
        $this->enableHtaccess();
        File::deleteDirectory($this->baseDirectory.'/install_files');
        File::delete($this->baseDirectory.'/install.php');
        $this->rewriteEnv([
            'APP_ENV' => 'production',
        ]);
        $this->sendEventMessage('complete', 'Process complete');

        $this->completeStep('clearAll');
    }

    private function bootZEDx()
    {
        $autoloadFile = $this->baseDirectory.'/bootstrap/autoload.php';
        if (!file_exists($autoloadFile)) {
            throw new Exception('Unable to find autoloader: ~/bootstrap/autoload.php');
        }

        require $autoloadFile;

        $appFile = $this->baseDirectory.'/bootstrap/app.php';
        if (!file_exists($appFile)) {
            throw new Exception('Unable to find app loader: ~/bootstrap/app.php');
        }

        $app = require_once $appFile;
        $kernel = $app->make('Illuminate\Contracts\Console\Kernel');
        $kernel->bootstrap();
    }

    private function getDatabaseConfigValues()
    {
        $config = array_merge([
            'type'   => null,
            'host'   => null,
            'name'   => null,
            'port'   => null,
            'user'   => null,
            'pass'   => null,
            'prefix' => null,
        ], [
            'type'   => $this->post('db_type'),
            'host'   => $this->post('db_host', ''),
            'name'   => $this->post('db_name', ''),
            'port'   => $this->post('db_port', ''),
            'user'   => $this->post('db_username', ''),
            'pass'   => $this->post('db_password', ''),
            'prefix' => 'zedx_'.$this->post('db_prefix', '').'_',
        ]);

        extract($config);

        switch ($type) {
            default:
            case 'mysql':
                $result = [
                    'DB_HOST'     => $host,
                    'DB_PORT'     => empty($port) ? 3306 : $port,
                    'DB_DATABASE' => $name,
                    'DB_USERNAME' => $user,
                    'DB_PASSWORD' => $pass,
                    'DB_PREFIX'   => $prefix,
                ];
                break;

            case 'sqlite':
                $result = [
                    'DB_DATABASE' => $name,
                    'DB_PREFIX'   => $prefix,
                ];
                break;

            case 'pgsql':
                $result = [
                    'DB_HOST'     => $host,
                    'DB_PORT'     => empty($port) ? 5432 : $port,
                    'DB_DATABASE' => $name,
                    'DB_USERNAME' => $user,
                    'DB_PASSWORD' => $pass,
                    'DB_PREFIX'   => $prefix,
                ];
                break;

            case 'sqlsrv':
                $result = [
                    'DB_HOST'     => $host,
                    'DB_PORT'     => empty($port) ? 1433 : $port,
                    'DB_DATABASE' => $name,
                    'DB_USERNAME' => $user,
                    'DB_PASSWORD' => $pass,
                    'DB_PREFIX'   => $prefix,
                ];
                break;
        }

        if (in_array($type, ['mysql', 'sqlite', 'pgsql', 'sqlsrv'])) {
            $result['DB_CONNECTION'] = $type;
        }

        return $result;
    }

    public function getBaseUrl()
    {
        if (isset($_SERVER['HTTP_HOST'])) {
            $baseUrl = !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' ? 'https' : 'http';
            $baseUrl .= '://'.$_SERVER['HTTP_HOST'];
            $baseUrl .= str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
        } else {
            $baseUrl = 'http://localhost/';
        }

        return $baseUrl;
    }

    private function validateSqliteFile($filename)
    {
        if (file_exists($filename)) {
            return;
        }

        $directory = dirname($filename);

        if (!is_dir($directory) && !mkdir($directory, 0777, true)) {
            throw new InstallerException("Can't create SQLite storage file", 'db_name');
        }

        new SQLite3($filename);
    }

    private function unzipFile($fileCode, $directory = null)
    {
        $source = $this->getFilePath($fileCode);
        $destination = $this->baseDirectory;

        $this->log('Extracting file (%s): %s', $fileCode, basename($source));

        if ($directory) {
            $destination .= '/'.$directory;
        }

        if (!file_exists($destination)) {
            mkdir($destination, 0777, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($source) === true) {
            $zip->extractTo($destination);
            $zip->close();

            return true;
        }

        return false;
    }

    private function disableHtaccess()
    {
        @unlink($this->baseDirectory.'/.htaccess');
    }

    private function enableHtaccess()
    {
        @copy($this->baseDirectory.'/default.htaccess', $this->baseDirectory.'/.htaccess');
    }

    private function getFilePath($fileCode)
    {
        $name = md5($fileCode).'.zip';

        return $this->tempDirectory.DIRECTORY_SEPARATOR.$name;
    }

    private function sendEventMessage($event, $message, $progress = 100)
    {
        if (!$this->initEventMessage) {
            @ob_start();
            echo str_repeat(' ', 2048).PHP_EOL;
            $this->initEventMessage = true;
        }

        $d = ['message' => $message, 'progress' => $progress];

        echo "event: $event".PHP_EOL;
        echo 'data: '.json_encode($d).PHP_EOL;
        echo PHP_EOL;

        @ob_flush();
        @flush();
    }

    private function progressCallback($resource, $download_size = 0, $downloaded = 0, $upload_size = 0, $uploaded = 0)
    {
        static $previousProgress = 0;

        if ($download_size == 0) {
            $progress = 0;
        } else {
            $progress = round($downloaded * 100 / $download_size, 2);
        }

        if ($progress > $previousProgress) {
            $previousProgress = $progress;
            $this->sendEventMessage('progress', 'Downloading '.$progress.' %', $progress);
            if ($progress >= 100) {
                $this->sendEventMessage('complete', 'Process complete');
            }
        }
    }

    private function requestServerFile($fileCode, $expectedHash, $uri = null, $params = [])
    {
        $result = null;
        $error = null;
        try {
            if (!is_dir($this->tempDirectory)) {
                $tempDirectory = mkdir($this->tempDirectory, 0777, true);
                if ($tempDirectory === false) {
                    $this->log('Failed to get create temporary directory: %s', $this->tempDirectory);
                    throw new Exception('Failed to get create temporary directory in '.$this->tempDirectory.'. Please ensure this directory is writable.');
                }
            }

            $filePath = $this->getFilePath($fileCode);
            $stream = fopen($filePath, 'w');

            $curl = $this->prepareServerRequest($uri, $params);
            curl_setopt($curl, CURLOPT_NOPROGRESS, false);
            curl_setopt($curl, CURLOPT_PROGRESSFUNCTION, [$this, 'progressCallback']);
            curl_setopt($curl, CURLOPT_FILE, $stream);
            curl_exec($curl);

            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if ($httpCode != 200) {
                $error = file_get_contents($filePath);
            }

            curl_close($curl);
            fclose($stream);
        } catch (Exception $ex) {
            $this->log('Failed to get server delivery: '.$ex->getMessage());
            throw new Exception('Server failed to deliver the package');
        }

        if ($error !== null) {
            throw new Exception('Server responded with error: '.$error);
        }

        $fileHash = md5_file($filePath);
        if ($expectedHash != $fileHash) {
            $this->log('File hash mismatch: %s (expected) vs %s (actual)', $expectedHash, $fileHash);
            $this->log('Local file size: %s', filesize($filePath));
            @unlink($filePath);
            throw new Exception('Package files from server are corrupt');
        }

        $this->log('Saving to file (%s): %s', $fileCode, $filePath);

        return true;
    }

    private function checkCode($code)
    {
        $this->log('System check: %s', $code);
        $result = false;
        switch ($code) {
            case 'liveConnection':
                $result = ($this->requestServerData() !== null);
                break;
            case 'writePermission':
                $result = is_writable($this->baseDirectory) && is_writable($this->logFile);
                break;
            case 'phpVersion':
                $result = version_compare(PHP_VERSION, '5.5.9', '>=');
                break;
            case 'pdoLibrary':
                $result = defined('PDO::ATTR_DRIVER_NAME');
                break;
            case 'mcryptLibrary':
                $result = extension_loaded('mcrypt');
                break;
            case 'mbstringLibrary':
                $result = extension_loaded('mbstring');
                break;
            case 'sslLibrary':
                $result = extension_loaded('openssl');
                break;
            case 'gdLibrary':
                $result = extension_loaded('gd');
                break;
            case 'curlLibrary':
                $result = function_exists('curl_init') && defined('CURLOPT_FOLLOWLOCATION');
                break;
            case 'zipLibrary':
                $result = class_exists('ZipArchive');
                break;
            case 'procOpen':
                $result = function_exists('proc_open');
                break;
            case 'symbolicLink':
                $result = function_exists('symlink');
                break;
        }

        $this->log('Requirement %s %s', $code, ($result ? '[ OK ]' : '[ FAIL ]'));

        return $result;
    }

    private function requestServerData($uri = '', $params = [])
    {
        $result = null;
        $error = null;
        try {
            $curl = $this->prepareServerRequest($uri, $params);
            $result = curl_exec($curl);

            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if ($httpCode == 500) {
                $error = $result;
                $result = '';
            }

            $this->log('Request information: %s', print_r(curl_getinfo($curl), true));

            curl_close($curl);
        } catch (Exception $ex) {
            $this->log('Failed to get server data (ignored): '.$ex->getMessage());
        }

        if ($error !== null) {
            throw new Exception('Server responded with error: '.$error);
        }

        if (!$result || !strlen($result)) {
            throw new Exception('Server responded had no response.');
        }

        try {
            $_result = @json_decode($result, true);
        } catch (Exception $ex) {
        }

        if (!is_array($_result)) {
            $this->log('Server response: '.$result);
            throw new Exception('Server returned an invalid response.');
        }

        return $_result;
    }

    private function prepareServerRequest($uri = '', $params = [])
    {
        $this->log('Server request: %s', ZEDx_GATEWAY.'/'.$uri);
        $params['url'] = base64_encode($this->getBaseUrl());
        $params['data'] = 'WIZARD';
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, ZEDx_GATEWAY.'/'.$uri);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 3600);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params, '', '&'));

        if (defined('ZEDx_GATEWAY_AUTH')) {
            curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($curl, CURLOPT_USERPWD, ZEDx_GATEWAY_AUTH);
        }

        return $curl;
    }

    private function post($var, $default = null)
    {
        if (array_key_exists($var, $_REQUEST)) {
            $result = $_REQUEST[$var];
            if (is_string($result)) {
                $result = trim($result);
            }

            return $result;
        }

        return $default;
    }

    private function rewriteEnv($newValues)
    {
        foreach ($newValues as $key => $value) {
            env_replace($key, $value);
        }

        $this->reloadConfig($newValues);
    }

    private function reloadConfig($configs)
    {
        foreach ($configs as $key => $value) {
            $code = null;

            if ($key == 'MAIL_FROM_ADDRESS') {
                $code = 'mail.from.address';
            }
            if ($key == 'MAIL_FROM_NAME') {
                $code = 'mail.from.name';
            }
            if ($key == 'APP_URL') {
                $code = 'app.url';
            }
            if ($key == 'APP_LOCALE') {
                $code = 'app.locale';
            }
            if ($key == 'APP_KEY') {
                $code = 'app.key';
            }
            if ($key == 'APP_ENV') {
                $code = 'app.env';
            }
            if ($key == 'DB_HOST') {
                $code = 'database.connections.'.$configs['DB_CONNECTION'].'.host';
            }
            if ($key == 'DB_PORT') {
                $code = 'database.connections.'.$configs['DB_CONNECTION'].'.port';
            }
            if ($key == 'DB_DATABASE') {
                $code = 'database.connections.'.$configs['DB_CONNECTION'].'.database';
            }
            if ($key == 'DB_USERNAME') {
                $code = 'database.connections.'.$configs['DB_CONNECTION'].'.username';
            }
            if ($key == 'DB_PASSWORD') {
                $code = 'database.connections.'.$configs['DB_CONNECTION'].'.password';
            }
            if ($key == 'DB_PREFIX') {
                $code = 'database.connections.'.$configs['DB_CONNECTION'].'.prefix';
            }
            if ($key == 'DB_CONNECTION') {
                $code = 'database.default';
            }

            if ($code !== null) {
                if ($code == 'database.connections.sqlite.database') {
                    $value = $this->databaseDirectory.DIRECTORY_SEPARATOR.$value;
                }

                config([$code => $value]);
            }
        }
    }

    //
    // Logging
    //

    public function cleanLog()
    {
        $message = [
            '.======================================================================.',
            '.                                                                      .',
            '.                                 ZEDx                                 .',
            '.                            http://zedx.io                            .',
            '.                                                                      .',
            '.========================== INSTALLATION LOG ==========================.',
            '',
        ];

        file_put_contents($this->logFile, implode(PHP_EOL, $message).PHP_EOL);

        $_SESSION['zedx-install-steps'] = [];
    }

    protected function stepCompleted($stepName)
    {
        return in_array($stepName, $_SESSION['zedx-install-steps']);
    }

    protected function completeStep($stepName)
    {
        array_push($_SESSION['zedx-install-steps'], $stepName);
    }

    public function log()
    {
        $args = func_get_args();
        $message = array_shift($args);

        if (is_array($message)) {
            $message = implode(PHP_EOL, $message);
        }

        $message = '['.date('Y/m/d h:i:s', time()).'] '.vsprintf($message, $args).PHP_EOL;
        file_put_contents($this->logFile, $message, FILE_APPEND);
    }
}
