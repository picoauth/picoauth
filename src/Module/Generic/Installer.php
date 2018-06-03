<?php
namespace PicoAuth\Module\Generic;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ParameterBag;
use PicoAuth\PicoAuthInterface;
use PicoAuth\Module\AbstractAuthModule;

/**
 * PicoAuth installation module
 */
class Installer extends AbstractAuthModule
{

    const CONFIG_PLUGIN_KEY = 'PicoAuth';
    const CONFIG_MODULES_KEY = 'authModules';

    /**
     * PicoAuth plugin
     * @var PicoAuthInterface
     */
    protected $picoAuth;

    /**
     * Modules supported by the installer script
     * Container-registered service names
     * @var array
     */
    protected $modules = array(
        "LocalAuth" => "LocalAuth",
        "OAuth" => "OAuth",
        "PageACL" => "PageACL",
        "PageLock" => "PageLock"
    );

    /**
     * Creates an installer
     *
     * @param PicoAuthInterface $picoAuth
     */
    public function __construct(PicoAuthInterface $picoAuth)
    {
        $this->picoAuth = $picoAuth;
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return 'installer';
    }

    /**
     * @inheritdoc
     */
    public function onPicoRequest($url, Request $httpRequest)
    {
        $post = $httpRequest->request;

        if ($url === "PicoAuth") {
            $this->picoAuth->setRequestFile($this->picoAuth->getPluginPath() . '/content/install.md');

            $this->checkServerConfiguration();
        } elseif ($url === "PicoAuth/modules") {
            $this->picoAuth->setRequestFile($this->picoAuth->getPluginPath() . '/content/install.md');

            if ($post->has("generate")) {
                $this->configGenerationAction($post);
            } else {
                $this->picoAuth->addOutput("installer_step", 1);
            }
        }
    }

    /**
     * Gets absolute Urls for pre-installation test
     *
     * In order to perform checks that the server configuration does not
     * allow public access to Pico configuration or content directory,
     * the tested Urls are passed to Javascript, where response
     * tests are performed using AJAX calls.
     *
     * The method assumes default locations of the config and content
     * directories, as specified in the Pico's index.php file.
     */
    protected function checkServerConfiguration()
    {
        $pico = $this->picoAuth->getPico();

        // Pico config.yml file
        $configDir = $pico->getBaseUrl() . basename($pico->getConfigDir());
        $configFile = $configDir . "/config.yml";

        // index.md file
        $contentDir = $pico->getBaseUrl() . basename($pico->getConfig('content_dir'));
        $indexFile = $contentDir . "/index" . $pico->getConfig('content_ext');

        $urls = array(
            'dir_listing' => $configDir,
            'config_file' => $configFile,
            'content_file' => $indexFile
        );

        $this->httpsTest();
        $this->webRootDirsTest();
        $this->picoAuth->addOutput("installer_urltest", $urls);
    }

    /**
     * Checks if this Pico installation uses https
     *
     * The resulting boolean value is set as a template variable
     * to be displayed by the installer.
     */
    protected function httpsTest()
    {
        $pico = $this->picoAuth->getPico();
        $url = $pico->getBaseUrl();
        
        $res = (substr($url, 0, 8) === "https://");

        $this->picoAuth->addOutput("installer_https", $res);
    }
    
    /**
     * Checks content and config dir locations
     *
     * Ideally, both of these directories should be outside the web server
     * root, which is Pico::getRootDir().
     * Adds the result to the output variables for display in the template.
     */
    protected function webRootDirsTest()
    {
        $pico = $this->picoAuth->getPico();
        
        $webRoot = realpath($pico->getRootDir());
        $configDir = realpath($pico->getConfigDir());
        $contentDir = realpath($pico->getConfig('content_dir'));
        
        // Webroot path must not be at the beginning of both of the paths
        $config = (substr($configDir, 0, strlen($webRoot)) !== $webRoot);
        $content = (substr($contentDir, 0, strlen($webRoot)) !== $webRoot);
        
        $this->picoAuth->addOutput("installer_wr_config", $config);
        $this->picoAuth->addOutput("installer_wr_content", $content);
    }

    /**
     * Form submission requesting to generate the plugin configuration
     *
     * @param ParameterBag $post
     */
    protected function configGenerationAction(ParameterBag $post)
    {
        //CSRF validation
        if (!$this->picoAuth->isValidCSRF($post->get("csrf_token"))) {
            // On a token mismatch the submission gets ignored
            $this->picoAuth->addOutput("installer_step", 1);
            return;
        }

        $this->picoAuth->addOutput("installer_step", 2);
        $this->outputModulesConfiguration($post);
    }

    /**
     * Creates a plugin configuration based on the selection
     *
     * @param ParameterBag $post
     */
    protected function outputModulesConfiguration(ParameterBag $post)
    {
        $modulesClasses = array();
        $modulesNames = array();

        foreach ($this->modules as $key => $value) {
            if ($post->has($key)) {
                $modulesClasses[] = $value;
                $modulesNames[] = $key;
            }
        }

        $config = array(
            self::CONFIG_PLUGIN_KEY => array(
                self::CONFIG_MODULES_KEY => $modulesClasses
            )
        );

        $yaml = \Symfony\Component\Yaml\Yaml::dump($config, 2, 4);

        // Adds output to the template variables
        $this->picoAuth->addOutput("installer_modules_config", $yaml);
        $this->picoAuth->addOutput("installer_modules_names", $modulesNames);
    }
}
