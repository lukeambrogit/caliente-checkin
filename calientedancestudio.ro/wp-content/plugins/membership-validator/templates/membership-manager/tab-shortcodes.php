<?php
defined('ABSPATH') || exit;
/** Shortcodes Help Tab - ORIGINAL CONTENT from shortcodes_page_callback */
?>

<h1><?php echo esc_html__('Membership Shortcodes', OC_TEXT_DOMAIN); ?></h1>

<div class="oc-shortcode-docs">
    <div class="oc-shortcode-section">
        <h2>Available Shortcode</h2>
        <p>Use this shortcode to display a complete membership page on your website.</p>
    </div>
    
    <div class="oc-shortcode-item">
        <h3><code>[membership_page]</code></h3>
        <p>🎯 <strong>SHORTCODE INTELIGENT</strong> - Detectează automat rolul utilizatorului și afișează conținutul potrivit:</p>
        
        <div class="oc-role-section">
            <h4>👤 Pentru UTILIZATORI NORMALI:</h4>
            <ul>
                <li>🎫 <strong>Propriile membership-uri</strong> - Progres și ședințe rămase</li>
                <li>📱 <strong>QR codes personale</strong> - Pentru validare la cursuri</li>
                <li>⏰ <strong>Expiry tracking</strong> - Notificări și status</li>
                <li>📞 <strong>Layout compact</strong> - Optimizat pentru mobile</li>
            </ul>
        </div>
        
        <div class="oc-role-section">
            <h4>👨‍💼 Pentru ADMINISTRATORI:</h4>
            <ul>
                <li>📊 <strong>TOATE membership-urile</strong> - Overview complet sistem</li>
                <li>👥 <strong>Management utilizatori</strong> - Inclusiv guest users</li>
                <li>🛠️ <strong>Tools administrative</strong> - Statistici și controale</li>
                <li>📱 <strong>Layout full</strong> - Detalii complete pentru management</li>
            </ul>
        </div>
        
        <h4>Utilizare ULTRA-SIMPLĂ:</h4>
        <div class="oc-shortcode-example" style="background: #28a745; color: white; padding: 15px; border-radius: 5px;">
            <strong>🎯 UN SINGUR SHORTCODE PENTRU TOT:</strong><br>
            <code style="background: white; color: #333; padding: 5px 10px; border-radius: 3px; font-size: 16px;">[membership_page]</code>
        </div>
        
        <p><strong>🚀 THAT'S IT!</strong> ZERO configurare necesară. Sistemul afișează automat:</p>
        <ul style="color: #28a745; font-weight: bold;">
            <li>✅ <strong>TOATE membership-urile</strong> (active + expirate)</li>
            <li>✅ <strong>Rolul utilizatorului</strong> (admin vs user)</li>
            <li>✅ <strong>Layout potrivit</strong> (full vs compact)</li>
            <li>✅ <strong>Funcționalități complete</strong> (management vs usage)</li>
            <li>✅ <strong>QR codes și statistici</strong> (automat pentru toți)</li>
        </ul>
        
        <div style="background: #e7f3ff; padding: 10px; border-left: 4px solid #007cba; margin: 10px 0;">
            <strong>💡 SIMPLU LA MAXIM:</strong> Nu mai trebuie să configurezi nimic! Shortcode-ul detectează automat ce să afișeze pentru fiecare utilizator.
        </div>
    </div>
    
    <div class="oc-shortcode-item">
        <h3>🎨 Styling</h3>
        <p>The shortcode includes responsive CSS that adapts to your theme. You can customize colors and layout using CSS:</p>
        <div class="oc-shortcode-example">
            <strong>CSS classes available:</strong><br>
            <code>.oc-membership-page</code> - Main container<br>
            <code>.oc-membership-card</code> - Individual membership cards<br>
            <code>.oc-qr-btn</code> - QR code buttons<br>
            <code>.oc-stats-overview</code> - Statistics section
        </div>
    </div>
</div>

<style>
.oc-shortcode-docs { max-width: 800px; }
.oc-shortcode-section { background: #f9f9f9; border-left: 4px solid #0073aa; padding: 15px; margin: 20px 0; }
.oc-shortcode-item { background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin: 20px 0; }
.oc-shortcode-item h3 { margin-top: 0; color: #23282d; }
.oc-shortcode-item code { background: #f0f0f1; padding: 2px 6px; border-radius: 3px; font-family: Consolas, Monaco, monospace; }
.oc-shortcode-example { background: #f8f8f9; border: 1px solid #e1e1e1; padding: 10px; border-radius: 3px; margin-top: 10px; }
.oc-shortcode-example code { background: transparent; color: #d63384; }
</style>
