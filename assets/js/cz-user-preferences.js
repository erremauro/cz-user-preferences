(function ($) {
  var BUBBLE_HIDE_DELAY_MS = 1400;
  var THEME_OVERRIDE_KEY = 'cz-theme-override';

  function getSameDomainReferrer() {
    if (!document.referrer) {
      return '';
    }

    try {
      var referrerUrl = new URL(document.referrer, window.location.origin);
      if (referrerUrl.origin !== window.location.origin) {
        return '';
      }
      if (referrerUrl.href === window.location.href) {
        return '';
      }
      return referrerUrl.href;
    } catch (e) {
      return '';
    }
  }

  function setupCloseButton($wrap) {
    var $button = $wrap.find('[data-czup-close]');
    if (!$button.length) {
      return;
    }

    var referrer = getSameDomainReferrer();
    if (!referrer) {
      $button.prop('hidden', true);
      return;
    }

    $button.prop('hidden', false);
    $button.on('click', function () {
      if (window.history.length > 1) {
        window.history.back();
        return;
      }
      window.location.href = referrer;
    });
  }

  function syncTextSizeValue($form) {
    var $range = $form.find('[name="text_size"]');
    var $value = $form.find('[data-czup-text-size-value]');
    var size = parseInt($range.val(), 10);
    var min = parseInt($range.attr('min'), 10);
    var max = parseInt($range.attr('max'), 10);
    var ratio;
    var trackWidth;
    var thumbSize;
    var leftPx;

    if (!$range.length || isNaN(size)) {
      return;
    }

    if (isNaN(min)) {
      min = 10;
    }
    if (isNaN(max) || max <= min) {
      max = 20;
    }

    ratio = (size - min) / (max - min);
    ratio = Math.min(1, Math.max(0, ratio));
    trackWidth = $range[0].offsetWidth || 0;
    thumbSize = 20;
    leftPx = ratio * Math.max(trackWidth - thumbSize, 0) + thumbSize / 2;

    if ($value.length) {
      $value.text(String(size));
      $value.css('left', leftPx + 'px');
    }
  }

  function showTextSizeBubble($form) {
    var $value = $form.find('[data-czup-text-size-value]');
    var timer = $form.data('czupBubbleTimer');

    if (timer) {
      window.clearTimeout(timer);
    }

    syncTextSizeValue($form);
    $value.addClass('is-visible');

    timer = window.setTimeout(function () {
      $value.removeClass('is-visible');
      $form.removeData('czupBubbleTimer');
    }, BUBBLE_HIDE_DELAY_MS);

    $form.data('czupBubbleTimer', timer);
  }

  function hideTextSizeBubbleNow($form) {
    var $value = $form.find('[data-czup-text-size-value]');
    var timer = $form.data('czupBubbleTimer');

    if (timer) {
      window.clearTimeout(timer);
      $form.removeData('czupBubbleTimer');
    }

    $value.removeClass('is-visible');
  }

  function setStatus($wrap, message, isError) {
    var $status = $wrap.find('[data-czup-status]');
    if (!$status.length) {
      return;
    }
    $status
      .toggleClass('czup-status--error', !!isError)
      .text(message || '');
  }

  function getScheduledTheme() {
    var hour = new Date().getHours();
    return (hour >= 7 && hour < 18) ? 'light' : 'dark';
  }

  function readThemeOverride() {
    var raw;
    var obj;

    try {
      raw = window.localStorage.getItem(THEME_OVERRIDE_KEY);
      if (!raw) {
        return null;
      }
      obj = JSON.parse(raw);
      if (!obj || (obj.exp || 0) < Date.now() || (obj.theme !== 'light' && obj.theme !== 'dark')) {
        window.localStorage.removeItem(THEME_OVERRIDE_KEY);
        return null;
      }
      return obj.theme;
    } catch (e) {
      try {
        window.localStorage.removeItem(THEME_OVERRIDE_KEY);
      } catch (_) {}
      return null;
    }
  }

  function syncThemeToggleButton(activeTheme, isForcedTheme) {
    var $btn = $('#theme-toggle');

    if (!$btn.length) {
      return;
    }

    $btn.attr('aria-pressed', activeTheme === 'dark' ? 'true' : 'false');

    if (isForcedTheme) {
      $btn.attr('aria-disabled', 'true');
      $btn.prop('disabled', true);
      return;
    }

    $btn.removeAttr('aria-disabled');
    $btn.prop('disabled', false);
  }

  function applyThemePreferenceLive(themeMode) {
    var root = document.documentElement;
    var mode = (themeMode || 'auto').toLowerCase();
    var isForcedTheme = mode === 'dark' || mode === 'light';
    var activeTheme;

    root.setAttribute('data-user-theme', mode);

    if (isForcedTheme) {
      activeTheme = mode;
      try {
        window.localStorage.removeItem(THEME_OVERRIDE_KEY);
      } catch (_) {}
    } else {
      activeTheme = readThemeOverride() || getScheduledTheme();
    }

    root.setAttribute('data-theme', activeTheme);
    syncThemeToggleButton(activeTheme, isForcedTheme);
  }

  function savePreferences($form) {
    if (!window.czupData || !czupData.isLoggedIn) {
      return;
    }

    var $wrap = $form.closest('[data-czup]');

    $.ajax({
      url: czupData.ajaxUrl,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'czup_save_preferences',
        nonce: czupData.nonce,
        text_size: $form.find('[name="text_size"]').val() || '',
        theme: $form.find('[name="theme"]').val() || '',
        show_quotes: $form.find('[name="show_quotes"]').is(':checked') ? '1' : '0',
        continue_reading: $form.find('[name="continue_reading"]').is(':checked') ? '1' : '0',
        show_readingtime: $form.find('[name="show_readingtime"]').is(':checked') ? '1' : '0'
      }
    })
      .done(function (response) {
        if (response && response.success) {
          setStatus($wrap, '', false);
          return;
        }
        setStatus($wrap, response && response.data && response.data.message ? response.data.message : 'Errore durante il salvataggio.', true);
      })
      .fail(function () {
        setStatus($wrap, 'Errore durante il salvataggio.', true);
      });
  }

  $(document).on('input', '[data-czup-form] input[type="range"]', function () {
    var $form = $(this).closest('[data-czup-form]');
    showTextSizeBubble($form);
  });

  $(document).on('focus', '[data-czup-form] input[type="range"]', function () {
    var $form = $(this).closest('[data-czup-form]');
    syncTextSizeValue($form);
  });

  $(document).on('blur', '[data-czup-form] input[type="range"]', function () {
    var $form = $(this).closest('[data-czup-form]');
    hideTextSizeBubbleNow($form);
  });

  $(document).on('change', '[data-czup-form] select, [data-czup-form] input', function () {
    var $form = $(this).closest('[data-czup-form]');
    var $field = $(this);

    if ($field.is('select[name="theme"]')) {
      applyThemePreferenceLive($field.val() || 'auto');
    }

    savePreferences($form);
  });

  $(document).on('submit', '[data-czup-form]', function (e) {
    e.preventDefault();
  });

  $(function () {
    $('[data-czup]').each(function () {
      setupCloseButton($(this));
    });

    $('[data-czup-form]').each(function () {
      var $form = $(this);
      var selectedTheme = $form.find('select[name="theme"]').val() || 'auto';
      syncTextSizeValue($form);
      applyThemePreferenceLive(selectedTheme);
    });
  });
})(jQuery);
