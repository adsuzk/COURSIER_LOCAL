(function (global) {
  'use strict';

  const LOCK_ATTR = 'lockedValue';
  const RAW_ATTR = 'rawValue';

  const PhoneUtils = {
    digitsOnly,
    stripCiPrefix,
    formatFromDigits,
    formatFromRaw,
    applySessionNumber,
    releaseSessionNumber,
    enforceFromDom,
  };

  function digitsOnly(value) {
    if (value == null) {
      return '';
    }
    return String(value).replace(/\D+/g, '');
  }

  function stripCiPrefix(digits) {
    let d = digits;
    if (d.startsWith('00225')) {
      d = d.slice(5);
    }
    if (d.startsWith('225') && d.length > 10) {
      d = d.slice(3);
    }
    if (d.length > 10) {
      d = d.slice(-10);
    }
    return d;
  }

  function formatFromDigits(digits) {
    if (!digits) {
      return '';
    }
    const blocks = digits.match(/.{1,2}/g) || [digits];
    return '+225 ' + blocks.join(' ');
  }

  function formatFromRaw(raw) {
    const digits = stripCiPrefix(digitsOnly(raw)).slice(-10);
    if (digits.length === 10) {
      return formatFromDigits(digits);
    }
    return raw && typeof raw === 'string' ? raw.trim() : String(raw ?? '');
  }

  function applySessionNumber(input, rawValue) {
    if (!input) {
      return;
    }
    attachMask(input);
    const formatted = formatFromRaw(rawValue);
    input.dataset.origin = 'session';
    input.dataset[RAW_ATTR] = rawValue ?? '';
    input.dataset[LOCK_ATTR] = formatted;
    input.value = formatted;
    input.readOnly = true;
    input.classList.add('readonly');
  }

  function releaseSessionNumber(input) {
    if (!input) {
      return;
    }
    attachMask(input);
    delete input.dataset.origin;
    delete input.dataset[LOCK_ATTR];
    delete input.dataset[RAW_ATTR];
    input.readOnly = false;
    input.classList.remove('readonly');
  }

  function enforceFromDom() {
    const senderInput = document.getElementById('senderPhone');
    if (!senderInput) {
      return;
    }
    attachMask(senderInput);
    if (senderInput.dataset.origin === 'session') {
      applySessionNumber(senderInput, senderInput.dataset[RAW_ATTR] || senderInput.value);
    }
  }

  function ensurePrefixForGuests(input) {
    if (!input || input.dataset.origin === 'session') {
      return;
    }
    if (!input.value || input.value.trim() === '') {
      input.value = '+225 ';
    }
  }

  function attachMask(input) {
    if (!input || input.__suzoskyMaskApplied) {
      return;
    }
    input.__suzoskyMaskApplied = true;

    input.addEventListener('focus', () => {
      ensurePrefixForGuests(input);
    });

    input.addEventListener('paste', (event) => {
      if (input.dataset.origin === 'session') {
        event.preventDefault();
        return;
      }
      setTimeout(() => {
        const digits = stripCiPrefix(digitsOnly(input.value)).slice(0, 10);
        input.value = digits ? formatFromDigits(digits) : '+225 ';
      }, 0);
    });

    input.addEventListener('input', () => {
      if (input.dataset.origin === 'session') {
        const locked = input.dataset[LOCK_ATTR] || '';
        if (input.value !== locked) {
          input.value = locked;
          console.warn('[OrderFormGuard] Sender phone is locked to the authenticated value.');
        }
        return;
      }
      const digits = stripCiPrefix(digitsOnly(input.value)).slice(0, 10);
      input.value = digits ? formatFromDigits(digits) : '+225 ';
    });

    input.addEventListener('blur', () => {
      if (input.dataset.origin === 'session') {
        const locked = input.dataset[LOCK_ATTR] || '';
        if (input.value !== locked) {
          input.value = locked;
        }
        return;
      }
      const digits = stripCiPrefix(digitsOnly(input.value)).slice(0, 10);
      input.value = digits ? formatFromDigits(digits) : '';
    });
  }

  document.addEventListener('DOMContentLoaded', enforceFromDom);
  global.addEventListener('pageshow', enforceFromDom);

  global.SuzoskyPhoneUtils = PhoneUtils;
})(window);
