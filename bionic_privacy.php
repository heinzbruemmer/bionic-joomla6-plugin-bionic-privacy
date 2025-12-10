<?php
/**
 * @package     Bionic Privacy Plugin
 * @subpackage  System
 * @copyright   Copyright (C) 2024 Bionic Technologies. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Log\Log;
use Joomla\CMS\User\UserHelper;

/**
 * Bionic Privacy Plugin
 *
 * @since  1.0.0
 */
class PlgSystemBionic_Privacy extends CMSPlugin
{
    /**
     * Application object
     *
     * @var    \Joomla\CMS\Application\CMSApplication
     * @since  1.0.0
     */
    protected $app;

    /**
     * Database object
     *
     * @var    \Joomla\Database\DatabaseDriver
     * @since  1.0.0
     */
    protected $db;

    /**
     * Load the language file on instantiation
     *
     * @var    boolean
     * @since  1.0.0
     */
    protected $autoloadLanguage = true;

    /**
     * Constructor
     *
     * @param   object  $subject  The object to observe
     * @param   array   $config   An array that holds the plugin configuration
     *
     * @since   1.0.0
     */
    public function __construct(&$subject, $config = array())
    {
        parent::__construct($subject, $config);
    }

    /**
     * Listener for the `onAfterRoute` event
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function onAfterRoute()
    {
        try {
            // Only run on site application
            if (!$this->app->isClient('site'))
            {
                return;
            }

            // Media files are now loaded in onBeforeCompileHead()
            // This ensures they are added to the document head correctly
        } catch (\Exception $e) {
            // Silent fail - log error if debug is enabled
            if (JDEBUG) {
                \Joomla\CMS\Log\Log::add('Bionic Privacy onAfterRoute: ' . $e->getMessage(), \Joomla\CMS\Log\Log::WARNING, 'jerror');
            }
        }
    }

    /**
     * Listener for the `onBeforeCompileHead` event
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function onBeforeCompileHead()
    {
        try {
            // Only run on site application
            if (!$this->app->isClient('site'))
            {
                return;
            }

            $doc = $this->app->getDocument();
            
            if (!$doc) {
                return;
            }

            // Add CSS
            $doc->addStyleSheet(\Joomla\CMS\Uri\Uri::root(true) . '/media/plg_system_bionic_privacy/css/bionic-privacy.css');
            
            // Add custom CSS if provided
            $customCss = $this->params->get('custom_css', '');
            if (!empty($customCss))
            {
                $doc->addStyleDeclaration($customCss);
            }

            // Add JavaScript
            $doc->addScript(\Joomla\CMS\Uri\Uri::root(true) . '/media/plg_system_bionic_privacy/js/bionic-privacy.js');

            // Add inline JavaScript configuration
            $this->addInlineConfig();
        } catch (\Exception $e) {
            // Silent fail - log error if debug is enabled
            if (JDEBUG) {
                \Joomla\CMS\Log\Log::add('Bionic Privacy onBeforeCompileHead: ' . $e->getMessage(), \Joomla\CMS\Log\Log::WARNING, 'jerror');
            }
        }
    }

    /**
     * Listener for the `onAfterRender` event
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function onAfterRender()
    {
        try {
            // Only run on site application
            if (!$this->app->isClient('site'))
            {
                return;
            }

            // Get the response body
            $body = $this->app->getBody();
            
            if (empty($body)) {
                return;
            }

            // Do not render banner on the configured privacy article page
            $privacyArticleId = (int) $this->params->get('privacy_article_id', 0);
            $input   = $this->app->input;
            $option  = $input->getCmd('option', '');
            $view    = $input->getCmd('view', '');
            $id      = $input->getInt('id', 0);

            if ($privacyArticleId && $option === 'com_content' && $view === 'article' && $id === $privacyArticleId)
            {
                return;
            }

            // Insert cookie banner before </body>
            $banner = $this->renderBanner();
            $body = str_replace('</body>', $banner . '</body>', $body);

            // Set the modified body
            $this->app->setBody($body);
        } catch (\Exception $e) {
            // Silent fail - log error if debug is enabled
            if (JDEBUG) {
                \Joomla\CMS\Log\Log::add('Bionic Privacy onAfterRender: ' . $e->getMessage(), \Joomla\CMS\Log\Log::WARNING, 'jerror');
            }
        }
    }

    /**
     * AJAX handler for consent logging
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function onAjaxBionicPrivacy()
    {
        // Get input
        $input = $this->app->input;
        $action = $input->get('action', '', 'cmd');
        $consent = $input->get('consent', '', 'cmd');

        // Validate
        if ($action !== 'log_consent' || !in_array($consent, ['accepted', 'declined']))
        {
            echo json_encode(['success' => false, 'message' => 'Invalid request']);
            $this->app->close();
        }

        // Log consent if enabled
        if ($this->params->get('log_consent', 1))
        {
            $this->logConsent($consent);
        }

        echo json_encode(['success' => true]);
        $this->app->close();
    }

    /**
     * Load media files (CSS and JavaScript)
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function loadMedia()
    {
        try {
            $doc = $this->app->getDocument();
            
            if (!$doc) {
                return;
            }
            
            $wa = $doc->getWebAssetManager();
            
            if (!$wa) {
                return;
            }

            // Register CSS
            $wa->registerAndUseStyle(
                'plg_system_bionic_privacy',
                'plg_system_bionic_privacy/bionic-privacy.css',
                [],
                [],
                []
            );

            // Add custom CSS if provided
            $customCss = $this->params->get('custom_css', '');
            if (!empty($customCss))
            {
                $wa->addInlineStyle($customCss);
            }

            // Add JavaScript directly (more reliable than WebAssetManager for some Joomla versions)
            $doc->addScript(\Joomla\CMS\Uri\Uri::root(true) . '/media/plg_system_bionic_privacy/js/bionic-privacy.js');
        } catch (\Exception $e) {
            // Silent fail - log error if debug is enabled
            if (JDEBUG) {
                \Joomla\CMS\Log\Log::add('Bionic Privacy: ' . $e->getMessage(), \Joomla\CMS\Log\Log::WARNING, 'jerror');
            }
        }
    }

    /**
     * Add inline JavaScript configuration
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function addInlineConfig()
    {
        try {
            $doc = $this->app->getDocument();
            
            if (!$doc) {
                return;
            }
            
            $config = [
                'cookieName' => 'bionic_cookie_consent',
                'cookieLifetime' => (int) $this->params->get('cookie_lifetime', 365),
                'ajaxUrl' => Uri::root() . 'index.php?option=com_ajax&plugin=bionic_privacy&format=json',
                'logConsent' => (bool) $this->params->get('log_consent', 1),
            ];

            $script = 'var BionicPrivacyConfig = ' . json_encode($config) . ';';
            $doc->addScriptDeclaration($script);
        } catch (\Exception $e) {
            // Silent fail - log error if debug is enabled
            if (JDEBUG) {
                \Joomla\CMS\Log\Log::add('Bionic Privacy Config: ' . $e->getMessage(), \Joomla\CMS\Log\Log::WARNING, 'jerror');
            }
        }
    }

    /**
     * Render the cookie banner HTML
     *
     * @return  string
     *
     * @since   1.0.0
     */
    protected function renderBanner()
    {
        $lang = $this->app->getLanguage();
        $langTag = $lang->getTag();
        
        // Determine language suffix for parameter lookup
        $langSuffix = (strpos($langTag, 'de-') === 0) ? 'de' : 'en';
        
        // Get texts (Hybrid: Parameter OR Language file)
        $title = $this->getText('title', $langSuffix, 'PLG_SYSTEM_BIONIC_PRIVACY_TITLE');
        $message = $this->getText('message', $langSuffix, 'PLG_SYSTEM_BIONIC_PRIVACY_MESSAGE');
        $accept = $this->getText('accept', $langSuffix, 'PLG_SYSTEM_BIONIC_PRIVACY_ACCEPT');
        $decline = $this->getText('decline', $langSuffix, 'PLG_SYSTEM_BIONIC_PRIVACY_DECLINE');
        $privacyLink = $this->getText('privacy_link', $langSuffix, 'PLG_SYSTEM_BIONIC_PRIVACY_PRIVACY_LINK');
        $details = $this->getText('details', $langSuffix, 'PLG_SYSTEM_BIONIC_PRIVACY_DETAILS');
        $detailsText = $this->getText('details_text', $langSuffix, 'PLG_SYSTEM_BIONIC_PRIVACY_DETAILS_TEXT');
        
        // Privacy article link
        $privacyArticleId = $this->params->get('privacy_article_id', 0);
        $privacyUrl = $privacyArticleId ? Route::_('index.php?option=com_content&view=article&id=' . $privacyArticleId) : '';
        
        // Show details toggle
        $showDetails = $this->params->get('show_details', 0);
        
        // Build HTML
        $html = [];
        $html[] = '<div id="bionic-cookie-banner" class="bionic-cookie-overlay" style="display: none;">';
        $html[] = '    <div class="bionic-cookie-modal">';
        $html[] = '        <div class="bionic-cookie-header">';
        $html[] = '            <h2 class="bionic-cookie-title">';
        $html[] = '                <span class="bionic-cookie-icon">🔒</span>';
        $html[] = '                ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $html[] = '            </h2>';
        $html[] = '        </div>';
        $html[] = '        <div class="bionic-cookie-body">';
        $html[] = '            <p class="bionic-cookie-message">';
        $html[] = '                ' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $html[] = '            </p>';
        
        if ($privacyUrl)
        {
            $html[] = '            <p class="bionic-cookie-link">';
            $html[] = '                <a href="' . $privacyUrl . '" target="_blank">';
            $html[] = '                    ' . htmlspecialchars($privacyLink, ENT_QUOTES, 'UTF-8');
            $html[] = '                </a>';
            $html[] = '            </p>';
        }
        
        if ($showDetails)
        {
            $html[] = '            <div class="bionic-cookie-details">';
            $html[] = '                <button type="button" class="bionic-cookie-details-toggle" onclick="BionicPrivacy.toggleDetails()">';
            $html[] = '                    ' . htmlspecialchars($details, ENT_QUOTES, 'UTF-8') . ' ▼';
            $html[] = '                </button>';
            $html[] = '                <div class="bionic-cookie-details-content" style="display: none;">';
            $html[] = '                    <p>' . htmlspecialchars($detailsText, ENT_QUOTES, 'UTF-8') . '</p>';
            $html[] = '                </div>';
            $html[] = '            </div>';
        }
        
        $html[] = '        </div>';
        $html[] = '        <div class="bionic-cookie-footer">';
        $html[] = '            <button type="button" class="bionic-cookie-btn bionic-cookie-btn-decline" onclick="BionicPrivacy.decline()">';
        $html[] = '                ' . htmlspecialchars($decline, ENT_QUOTES, 'UTF-8');
        $html[] = '            </button>';
        $html[] = '            <button type="button" class="bionic-cookie-btn bionic-cookie-btn-accept" onclick="BionicPrivacy.accept()">';
        $html[] = '                ' . htmlspecialchars($accept, ENT_QUOTES, 'UTF-8');
        $html[] = '            </button>';
        $html[] = '        </div>';
        $html[] = '    </div>';
        $html[] = '</div>';
        
        return implode("\n", $html);
    }

    /**
     * Get text (Hybrid: Parameter OR Language file)
     *
     * @param   string  $key         Parameter key
     * @param   string  $langSuffix  Language suffix (de/en)
     * @param   string  $langKey     Language constant key
     *
     * @return  string
     *
     * @since   1.0.0
     */
    protected function getText($key, $langSuffix, $langKey)
    {
        // Try to get from parameter first
        $paramKey = $key . '_' . $langSuffix;
        $paramValue = $this->params->get($paramKey, '');
        
        // If parameter is set, use it; otherwise use language file
        return !empty($paramValue) ? $paramValue : Text::_($langKey);
    }

    /**
     * Log consent to database and action log
     *
     * @param   string  $consent  Consent type (accepted/declined)
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function logConsent($consent)
    {
        try
        {
            $user = $this->app->getIdentity();
            $userId = $user->id;
            $ip = $this->app->input->server->get('REMOTE_ADDR', '', 'string');
            $userAgent = $this->app->input->server->get('HTTP_USER_AGENT', '', 'string');
            
            // Generate subject_id (unique identifier for consent)
            $subjectId = $userId > 0 ? 'user_' . $userId : 'guest_' . UserHelper::genRandomPassword(16);
            
            // Subject for core privacy consents table
            \$subject = 'Bionic Cookie Consent';
            
            // Store in action log
            if (class_exists('Joomla\Component\Actionlogs\Administrator\Model\ActionlogModel'))
            {
                $message = [
                    'action' => 'cookie_consent_' . $consent,
                    'id' => 0,
                    'title' => 'Cookie Consent: ' . ucfirst($consent),
                    'itemlink' => '',
                    'userid' => $userId,
                    'username' => $userId > 0 ? $user->username : 'Guest',
                    'accountlink' => '',
                ];
                
                Log::add(
                    'Cookie consent ' . $consent . ' from IP: ' . $ip,
                    Log::INFO,
                    'plg_system_bionic_privacy'
                );
            }
            
            // Could also store in privacy_consents table if exists
            // This would require checking if com_privacy is installed
            $query = $this->db->getQuery(true);
            
            // Check if privacy_consents table exists
            $tables = $this->db->getTableList();
            $prefix = $this->db->getPrefix();
            
            if (in_array($prefix . 'privacy_consents', $tables))
            {
                $query->insert($this->db->quoteName('#__privacy_consents'))
                    ->columns([
                        $this->db->quoteName('user_id'),
                        $this->db->quoteName('subject'),
                        $this->db->quoteName('body'),
                        $this->db->quoteName('created'),
                        $this->db->quoteName('state'),
                        $this->db->quoteName('remind'),
                        $this->db->quoteName('token')
                    ])
                    ->values(
                        $this->db->quote($userId) . ', ' .
                        $this->db->quote($subject) . ', ' .
                        $this->db->quote(json_encode([
                            'consent' => $consent,
                            'ip' => $ip,
                            'user_agent' => $userAgent,
                            'subject_id' => $subjectId,
                            'timestamp' => Factory::getDate()->toSql()
                        ])) . ', ' .
                        $this->db->quote(Factory::getDate()->toSql()) . ', ' .
                        '1, ' .
                        '0, ' .
                        $this->db->quote(UserHelper::genRandomPassword(32))
                    );
                
                $this->db->setQuery($query);
                $this->db->execute();
            }
        }
        catch (Exception $e)
        {
            // Silently fail - logging is not critical
            Log::add(
                'Error logging consent: ' . $e->getMessage(),
                Log::ERROR,
                'plg_system_bionic_privacy'
            );
        }
    }
}
