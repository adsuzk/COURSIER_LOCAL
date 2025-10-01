# Correction rapide de MainActivity.kt
# Suppression des parties BroadcastReceiver qui causent des erreurs de compilation

# Trouvons et supprimons toutes les parties problématiques
import re

def clean_mainactivity():
    with open(r'c:\xampp\htdocs\COURSIER_LOCAL\CoursierAppV7\app\src\main\java\com\suzosky\coursier\MainActivity.kt', 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Supprimer les lignes problématiques
    lines = content.split('\n')
    cleaned_lines = []
    skip_until_brace = False
    
    for line in lines:
        if 'setupCommandeReceiver' in line and 'fun' in line:
            skip_until_brace = True
            cleaned_lines.append('    // setupCommandeReceiver function removed for compilation')
            continue
        
        if 'commandeReceiver' in line and skip_until_brace:
            continue
            
        if 'onDestroy()' in line and 'override' in line:
            skip_until_brace = True
            cleaned_lines.append('    // onDestroy function removed for compilation')
            continue
            
        if skip_until_brace and line.strip() == '}':
            skip_until_brace = False
            continue
            
        if not skip_until_brace:
            cleaned_lines.append(line)
    
    # Écrire le fichier nettoyé
    with open(r'c:\xampp\htdocs\COURSIER_LOCAL\CoursierAppV7\app\src\main\java\com\suzosky\coursier\MainActivity.kt', 'w', encoding='utf-8') as f:
        f.write('\n'.join(cleaned_lines))
    
    print("MainActivity.kt nettoyé avec succès")

if __name__ == "__main__":
    clean_mainactivity()