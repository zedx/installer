# ZEDx wizard installation

The wizard installation is a recommended way to install ZEDx. It is simpler than the command-line installation and doesn't require any special skills.

- Prepare a directory on your server that is empty. It can be a sub-directory, domain root or a sub-domain.
- [Download the installer archive file](https://github.com/zedx/installer/archive/master.zip).
- Unpack the installer archive to the prepared directory.
- Grant writing permissions on the installation directory and all its subdirectories and files.
- Navigate to the install.php script in your web browser.
- Follow the installation instructions.

## Minimum System Requirements

ZEDx CMS has a few system requirements:

* PHP 5.5.9 or higher
* PDO PHP Extension
* cURL PHP Extension
* MCrypt PHP Extension
* ZipArchive PHP Library
* GD PHP Library

As of PHP 5.5, some OS distributions may require you to manually install the PHP JSON extension.
When using Ubuntu, this can be done via ``apt-get install php5-json``.


![image](https://github.com/zedx/docs/blob/master/images/wizard-installer.png?raw=true)

> **Note:** A detailed installation log can be found in the `install_files/install.log` file.
