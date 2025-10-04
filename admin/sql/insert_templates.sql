-- Insert sample templates
INSERT INTO email_templates (name, type, subject_template, html_content, text_content, variables, is_active) VALUES 
('Bienvenue Client', 'welcome', 'Bienvenue sur Conciergerie Privée Suzosky !', 
'<h2>Bonjour {{nom}},</h2><p>Nous sommes ravis de vous accueillir parmi nos clients !</p><p>Votre compte a été créé avec succès. Vous pouvez dès maintenant profiter de tous nos services de conciergerie.</p><p>Cordialement,<br>L''équipe Suzosky</p>', 
'Bonjour {{nom}},\n\nNous sommes ravis de vous accueillir parmi nos clients !\n\nVotre compte a été créé avec succès.\n\nCordialement,\nL''équipe Suzosky', 
'{"nom": "Nom du client", "prenom": "Prénom", "email": "Email"}', 1),

('Confirmation Commande', 'notification', 'Votre commande #{{commande_id}} est confirmée', 
'<h2>Bonjour {{nom}},</h2><p>Votre commande <strong>#{{commande_id}}</strong> a été confirmée.</p><p><strong>Montant:</strong> {{montant}} FCFA<br><strong>Statut:</strong> {{statut}}</p><p>Merci de votre confiance !</p>', 
'Bonjour {{nom}},\n\nVotre commande #{{commande_id}} a été confirmée.\nMontant: {{montant}} FCFA\nStatut: {{statut}}\n\nMerci de votre confiance !', 
'{"nom": "Nom", "commande_id": "ID commande", "montant": "Montant", "statut": "Statut"}', 1),

('Newsletter Mensuelle', 'campaign', 'Actualités de {{site_name}} - {{date}}', 
'<h2>Bonjour {{prenom}},</h2><p>Découvrez les nouveautés de ce mois !</p><p>Restez connecté pour ne rien manquer.</p><p>L''équipe {{site_name}}</p>', 
'Bonjour {{prenom}},\n\nDécouvrez les nouveautés de ce mois !\n\nRestez connecté pour ne rien manquer.\n\nL''équipe {{site_name}}', 
'{"prenom": "Prénom", "site_name": "Nom du site", "date": "Date"}', 1);
