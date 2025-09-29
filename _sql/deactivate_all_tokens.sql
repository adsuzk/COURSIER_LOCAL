-- Script SQL pour désactiver tous les tokens FCM (présence coursier)
UPDATE device_tokens SET is_active = 0;
