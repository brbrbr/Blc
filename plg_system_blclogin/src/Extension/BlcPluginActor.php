<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.sef
 *
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Blc\Plugin\System\Blclogin\Extension;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


use Blc\Component\Blc\Administrator\Blc\BlcPlugin;
use Blc\Component\Blc\Administrator\Blc\BlcTransientManager;
use Blc\Component\Blc\Administrator\Checker\BlcCheckerHttpCurl;
use Blc\Component\Blc\Administrator\Helper\BlcHelper;
use Blc\Component\Blc\Administrator\Interface\BlcCheckerInterface;
use Blc\Component\Blc\Administrator\Table\LinkTable;
use Blc\Component\Blc\Administrator\Traits\BlcHelpTrait;
use Joomla\CMS\Authentication\Authentication;
use Joomla\CMS\Authentication\AuthenticationResponse;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Event\User\LoginEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\UserFactoryAwareTrait;
use Joomla\CMS\User\UserHelper;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use Joomla\Utilities\IpHelper;

final class BlcPluginActor extends BlcPlugin implements SubscriberInterface, BlcCheckerInterface
{
    use UserFactoryAwareTrait;
    use BlcHelpTrait;

    private const  HELPLINK = 'https://brokenlinkchecker.dev/extensions/plg-system-blclogin';

    protected $context = 'x-blc-login';
    /**
     * Add the canonical uri to the head.
     *
     * @return  void
     *
     * @since   3.5
     */
    public static function getSubscribedEvents(): array
    {

        //this should prevent server faults when the component is deinstalled.
        if (!ComponentHelper::isEnabled('com_blc')) {
            return [];
        }
        return [
            'onBlcCheckerRequest' => 'onBlcCheckerRequest',
            'onAfterRoute'        => 'onAfterRoute',
        ];
    }
    public function onAfterRoute(): void
    {

        $app = Factory::getApplication();
        if ($app->isClient('administrator')) {
            return;
        }

        $user = $app->getIdentity();
        if (!$user->guest) {
            return;
        }
        $allowIp     = $this->params->get('ip', '');
        $curlChecker = BlcCheckerHttpCurl::getInstance();
        $header      = md5($curlChecker->useragent . $allowIp);
        $headers     = array_change_key_case($app->client->headers);

        if (!isset($headers[$header])) {
            return;
        }

        $this->setTransientIp('REQUEST');

        $currentIp = BlcHelper::getIp();
        if ($allowIp && !IpHelper::IPinList($currentIp, $allowIp)) {
            $this->setTransientIp('FAILED - IP');
            return;
        }
        $user = $this->params->get('user', 0);
        if (!$user) {
            $this->setTransientIp('FAILED - USER');
            return;
        }

        $transientmanager = BlcTransientManager::getInstance();
        $OTP              = $headers[$header];
        $transient        = "OTP:$header";
        $hashedOTP        = $transientmanager->get($transient);
        if (!UserHelper::verifyPassword($OTP, $hashedOTP, $user)) {
            return;
        }
        $this->userLogin($user);
    }

    private function userLogin(int $userId)
    {
        Authentication::getInstance();
        PluginHelper::importPlugin('user');
        $user = $this->getUserFactory()->loadUserById($userId);

        // Construct the options
        $options = [
            'action'       => 'core.login.site',
            'group'        => 'Public Frontend',
            'autoregister' => '',
        ];

        // Construct the response-object
        $response                = new AuthenticationResponse();
        $response->type          = 'Joomla';
        $response->email         = $user->email;
        $response->fullname      = $user->name;
        $response->username      = $user->username;
        $response->password      = $user->password;
        $response->language      = $user->getParam('language');
        $response->status        = Authentication::STATUS_SUCCESS;
        $response->error_message = null;


        if (class_exists('Joomla\CMS\Event\User\LoginEvent')) {
            $loginEvent = new LoginEvent('onUserLogin', ['subject' => (array) $response, 'options' => $options]);
        } else {
            $loginEvent = new Event('onUserLogin', ['subject' => (array) $response, 'options' => $options]);
        }
        // Run the login-event
        Factory::getApplication()->getDispatcher()->dispatch('onUserLogin', $loginEvent);
    }

    protected function setTransientIp(string $status)
    {
        $date             = new Date();
        $unix             = $date->toUnix();
        $transientmanager = BlcTransientManager::getInstance();
        $data             = [
            'ip'     => BlcHelper::getIp(),
            'last'   => $unix,
            'status' => $status,
        ];
        $transient = "BLC LOGIN REQUEST";
        $transientmanager->set($transient, $data, true);
    }

    public function onBlcCheckerRequest($event): void
    {
        $checker = $event->getItem();
        $checker->registerChecker($this, 45); //before the http checker (50)
    }

    public function canCheckLink(LinkTable $linkItem): int
    {
        if ($linkItem->isInternal()) {
            return self::BLC_CHECK_CONTINUE;
        }
        return self::BLC_CHECK_FALSE;
    }

    public function checkLink(LinkTable &$linkItem, $results = []): array
    {
        if (!$linkItem->isInternal()) {
            return  $results;
        }
        $curlChecker = BlcCheckerHttpCurl::getInstance();
        $user        = $this->params->get('user', 0);
        if ($user) {
            $allowIp   = $this->params->get('ip', '');
            $header    = md5($curlChecker->useragent . $allowIp);
            $OTP       = UserHelper::genRandomPassword(20);
            $hashedOTP = UserHelper::hashPassword($OTP);
            $curlChecker->addHeader($header . ': ' . $OTP);
            $transientmanager = BlcTransientManager::getInstance();
            $transient        = "OTP:$header";
            $transientmanager->set($transient, $hashedOTP, 60);
        }
        return  $results;
    }
    public function setConfig(Registry $config): void
    {
    }
}
