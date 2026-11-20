/**
 * @file
 * GLightbox JS.
 */

/* global DOMPurify, GLightbox */

(function (Drupal, drupalSettings, once) {
  // Keep strict mode because this Drupal asset is a classic script.
  /* eslint-disable strict */

  'use strict';

  /* eslint-enable strict */

  // Create glightbox namespace if it doesn't exist.
  if (!Drupal.hasOwnProperty('glightbox')) {
    Drupal.glightbox = {};
  }

  /**
   * Global function to allow sanitizing captions and control strings.
   *
   * @param markup
   *   String containing potential markup.
   * @return {string}
   *   Sanitized string with potentially dangerous markup removed.
   */
  Drupal.glightbox.sanitizeMarkup = function (markup) {
    if (typeof DOMPurify !== 'undefined') {
      let purifyConfig = {
        ALLOWED_TAGS: [
          'a',
          'b',
          'strong',
          'i',
          'em',
          'u',
          'cite',
          'code',
          'br',
          'p',
          'div',
          'h1',
          'h2',
          'h3',
          'h4',
          'span',
        ],
        ALLOWED_ATTR: ['href', 'hreflang', 'title', 'target'],
      };
      if (
        typeof drupalSettings !== 'undefined' &&
        drupalSettings.hasOwnProperty('dompurify_custom_config')
      ) {
        purifyConfig = drupalSettings.dompurify_custom_config;
      }
      return DOMPurify.sanitize(markup, purifyConfig);
    }
    if (typeof Drupal.checkPlain === 'function') {
      return Drupal.checkPlain(markup);
    }

    // Fallback: basic escaping.
    return String(markup).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  };

  /**
   * Sanitize a user-supplied href.
   * - Allows absolute http/https/ftp/ftps only (per SAFE_PROTOCOL_RE).
   * - Drops *all* relative or protocol-relative URLs (“/foo”, “//cdn…”, “../bar”, …).
   * - Returns the cleaned, canonicalized URL string, or an empty string when disallowed.
   * @param rawHref
   */
  Drupal.glightbox.sanitizeHref = function (rawHref) {
    if (typeof rawHref !== 'string') {
      return '';
    }

    // Strip obvious obfuscation.
    const clean = rawHref
      // Null bytes.
      // eslint-disable-next-line no-control-regex -- Remove null bytes before URL parsing.
      .replace(/\u0000/g, '')
      // Control chars.
      .replace(/[\t\n\r]/g, '')
      .trim();

    const SAFE_PROTOCOL_RE =
      /^(?:(?:(?:f|ht)tps?):|[^a-z]|[a-z+.-]+(?:[^-a-z+.:]|$))/i;
    // Fast-fail if protocol isn’t in the allow-list.
    if (!SAFE_PROTOCOL_RE.test(clean)) {
      return '';
    }

    // Require the string to *explicitly* contain a protocol.
    // Skip relative / protocol-relative URLs.
    if (!/^[a-z][a-z0-9+.-]*:/.test(clean)) {
      return '';
    }

    // Let the URL constructor canonicalize and validate.
    try {
      // Absolute URL required.
      const url = new URL(clean);
      return url.href;
    } catch {
      // Malformed, unreachable, etc.
      return '';
    }
  };

  Drupal.behaviors.initGLightbox = {
    attach(context, settings) {
      // Sanitize data-title and data-description attributes on all .glightbox elements before GLightbox is initialized.
      document.querySelectorAll('.glightbox').forEach((el) => {
        el.getAttributeNames()
          .filter((name) => name.startsWith('data-'))
          .forEach((name) => {
            const rawValue = el.getAttribute(name);
            let clean = Drupal.glightbox.sanitizeMarkup(rawValue);
            if (name === 'data-href') {
              clean = Drupal.glightbox.sanitizeHref(clean);
            }
            el.setAttribute(name, clean);
          });
      });
      const options = settings.glightbox || {};
      const lightbox = GLightbox(options);

      lightbox.on('slide_changed', ({ prev, current }) => {
        const { slideIndex, slideNode, slideConfig, player } = current;

        // Video autoplay if it's possible.
        if (player) {
          if (!player.ready) {
            // If player is not ready.
            player.on('ready', (event) => {
              // Do something when video is ready.
            });
          }

          player.on('play', (event) => {
            // console.log('Started play');
          });

          player.on('volumechange', (event) => {
            // console.log('Volume change');
          });

          player.on('ended', (event) => {
            // console.log('Video ended');
          });
        }
      });
    },
  };
})(Drupal, drupalSettings, once);
