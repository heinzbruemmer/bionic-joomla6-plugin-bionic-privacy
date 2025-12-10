/**
 * Bionic Privacy - Cookie Management
 *
 * @package     Bionic Privacy Plugin
 * @copyright   Copyright (C) 2024 Bionic Technologies
 * @license     GNU General Public License version 2 or later
 */

var BionicPrivacy = BionicPrivacy || {};

(function() {
    'use strict';
    
    /**
     * Initialize
     */
    BionicPrivacy.init = function() {
        // Check consent on page load
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', BionicPrivacy.checkConsent);
        } else {
            BionicPrivacy.checkConsent();
        }
        
        // Prevent closing banner with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                var banner = document.getElementById('bionic-cookie-banner');
                if (banner && banner.style.display !== 'none') {
                    e.preventDefault();
                }
            }
        });
    };
    
    /**
     * Check if consent exists
     */
    BionicPrivacy.checkConsent = function() {
        var consent = BionicPrivacy.getCookie(BionicPrivacyConfig.cookieName);
        
        if (consent === null) {
            // No consent yet - show banner
            BionicPrivacy.showBanner();
        } else if (consent === 'accepted') {
            // Consent given - enable analytics
            BionicPrivacy.enableAnalytics();
        }
        // If declined, do nothing
    };
    
    /**
     * Show banner
     */
    BionicPrivacy.showBanner = function() {
        var banner = document.getElementById('bionic-cookie-banner');
        if (banner) {
            banner.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    };
    
    /**
     * Hide banner
     */
    BionicPrivacy.hideBanner = function() {
        var banner = document.getElementById('bionic-cookie-banner');
        if (banner) {
            banner.style.display = 'none';
            document.body.style.overflow = '';
        }
    };
    
    /**
     * Accept cookies
     */
    BionicPrivacy.accept = function() {
        BionicPrivacy.setCookie(BionicPrivacyConfig.cookieName, 'accepted', BionicPrivacyConfig.cookieLifetime);
        BionicPrivacy.enableAnalytics();
        BionicPrivacy.hideBanner();
        
        // Log consent
        if (BionicPrivacyConfig.logConsent) {
            BionicPrivacy.logConsent('accepted');
        }
        
        // Dispatch event
        window.dispatchEvent(new CustomEvent('bionicPrivacyAccepted'));
    };
    
    /**
     * Decline cookies
     */
    BionicPrivacy.decline = function() {
        BionicPrivacy.setCookie(BionicPrivacyConfig.cookieName, 'declined', BionicPrivacyConfig.cookieLifetime);
        BionicPrivacy.hideBanner();
        
        // Log consent
        if (BionicPrivacyConfig.logConsent) {
            BionicPrivacy.logConsent('declined');
        }
        
        // Dispatch event
        window.dispatchEvent(new CustomEvent('bionicPrivacyDeclined'));
    };
    
    /**
     * Enable analytics
     */
    BionicPrivacy.enableAnalytics = function() {
        // Google Analytics (GA4)
        if (typeof gtag !== 'undefined') {
            gtag('consent', 'update', {
                'analytics_storage': 'granted'
            });
        }
        
        // Matomo
        if (typeof _paq !== 'undefined') {
            _paq.push(['rememberConsentGiven']);
        }
        
        // Google Tag Manager
        if (typeof dataLayer !== 'undefined') {
            dataLayer.push({
                'event': 'cookie_consent_granted'
            });
        }
        
        // Dispatch event for custom analytics
        window.dispatchEvent(new CustomEvent('bionicPrivacyAnalyticsEnabled'));
        
        console.log('Bionic Privacy: Analytics enabled');
    };
    
    /**
     * Toggle details section
     */
    BionicPrivacy.toggleDetails = function() {
        var content = document.querySelector('.bionic-cookie-details-content');
        var toggle = document.querySelector('.bionic-cookie-details-toggle');
        
        if (content && toggle) {
            if (content.style.display === 'none') {
                content.style.display = 'block';
                toggle.textContent = toggle.textContent.replace('▼', '▲');
            } else {
                content.style.display = 'none';
                toggle.textContent = toggle.textContent.replace('▲', '▼');
            }
        }
    };
    
    /**
     * Revoke consent (for privacy page)
     */
    BionicPrivacy.revoke = function() {
        document.cookie = BionicPrivacyConfig.cookieName + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
        location.reload();
    };
    
    /**
     * Log consent via AJAX
     */
    BionicPrivacy.logConsent = function(consent) {
        if (!BionicPrivacyConfig.ajaxUrl) {
            return;
        }
        
        fetch(BionicPrivacyConfig.ajaxUrl + '&action=log_consent&consent=' + consent, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin'
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                console.log('Bionic Privacy: Consent logged');
            }
        })
        .catch(function(error) {
            console.error('Bionic Privacy: Error logging consent:', error);
        });
    };
    
    /**
     * Set cookie
     */
    BionicPrivacy.setCookie = function(name, value, days) {
        var expires = new Date();
        expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = name + '=' + value + ';expires=' + expires.toUTCString() + ';path=/;SameSite=Lax';
    };
    
    /**
     * Get cookie
     */
    BionicPrivacy.getCookie = function(name) {
        var nameEQ = name + '=';
        var ca = document.cookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    };
    
    // Auto-initialize
    BionicPrivacy.init();
    
})();
