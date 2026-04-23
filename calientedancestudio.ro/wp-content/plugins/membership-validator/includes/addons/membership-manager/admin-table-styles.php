<?php
/**
 * CSS pentru Admin Table - COPIAT DIN ORIGINAL
 */

if (!defined('ABSPATH')) {
    exit;
}

function get_admin_table_styles() {
    return "
    <style>
    .oc-admin-table-container {
        background: #fff;
        border-radius: 4px;
        padding: 20px;
    }
    
    .oc-table-header {
        margin-bottom: 20px;
        border-bottom: 1px solid #e1e1e1;
        padding-bottom: 15px;
        margin-left: 20px;
    }
    
    .oc-search-container {
        margin: 15px 0;
    }
    
    .oc-search-form {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .oc-search-input {
        flex: 1;
        max-width: 400px;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    /* Container List - Full Width */
    .oc-admin-cards-grid {
        display: flex;
        flex-direction: column;
        gap: 16px;
        margin: 20px 0;
        width: 100%;
    }
    
    /* Single Admin Card - COPIAT DIN ORIGINAL */
    .oc-admin-card {
        background: #fff;
        border: 1px solid #e1e1e1;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
        transition: all 0.2s ease;
    }
    
    .oc-admin-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    }
    
    /* 🎯 v1.3.0: Color Coding pentru Status - conform plan linia 214-218 */
    
    /* Verde: Activ cu > 7 zile până la expirare */
    .oc-admin-card.status-active {
        border-left: 4px solid #28a745 !important;
        background: linear-gradient(to right, #f0fff4 0%, white 10%);
    }
    
    /* Orange: Activ cu < 7 zile până la expirare (ATENȚIE!) */
    .oc-admin-card.status-expires-soon {
        border-left: 4px solid #ff9800 !important;
        background: linear-gradient(to right, #fff3e0 0%, white 10%);
    }
    
    /* Roșu: Expirat */
    .oc-admin-card.status-expired {
        border-left: 4px solid #dc3545 !important;
        background: linear-gradient(to right, #fff5f5 0%, white 10%);
        opacity: 0.85;
    }
    
    /* Albastru: Pending (așteaptă activare) */
    .oc-admin-card.status-pending {
        border-left: 4px solid #0073aa !important;
        background: linear-gradient(to right, #e7f3ff 0%, white 10%);
    }
    
    /* Hover effects pentru fiecare status */
    .oc-admin-card.status-active:hover {
        box-shadow: 0 4px 12px rgba(40,167,69,0.2);
    }
    
    .oc-admin-card.status-expires-soon:hover {
        box-shadow: 0 4px 12px rgba(255,152,0,0.3);
    }
    
    .oc-admin-card.status-expired:hover {
        box-shadow: 0 4px 12px rgba(220,53,69,0.2);
    }
    
    .oc-admin-card.status-pending:hover {
        box-shadow: 0 4px 12px rgba(0,115,170,0.2);
    }
    
    /* Card Header cu Info Complete */
    .oc-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 16px 20px;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        gap: 20px;
    }
    
    .oc-card-main-info {
        flex: 1;
        min-width: 0;
        display: grid;
        grid-template-columns: minmax(200px, 240px) minmax(0, 1fr);
        gap: 20px;
    }
    
    /* Member Identity Section */
    .oc-member-identity {
        min-width: 200px;
    }
    
    .oc-member-name {
        font-size: 16px;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .oc-guest-badge {
        background: #e74c3c;
        color: white;
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 8px;
        font-weight: 500;
        text-transform: uppercase;
    }
    
    .oc-member-email {
        font-size: 13px;
        color: #6c757d;
        font-weight: 400;
    }
    
    /* Membership Info Section - Grid Layout */
    .oc-membership-info {
        flex: 1;
        min-width: 0;
        display: grid;
        grid-template-columns: 2fr 2fr 1.5fr 1.5fr;
        gap: 16px;
        font-size: 13px;
        line-height: 1.4;
        align-items: start;
    }
    
    .oc-subscription-type,
    .oc-course-variation,
    .oc-sessions-info,
    .oc-validity-info {
        color: #495057;
    }
    
    .oc-sessions-used {
        color: #dc3545;
        font-weight: 600;
    }
    
    .oc-sessions-total {
        color: #007cba;
        font-weight: 600;
    }
    
    .oc-sessions-remaining {
        color: #28a745;
        font-weight: 600;
    }
    
    /* Card Actions Section */
    .oc-card-actions {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 12px;
        flex: 0 0 220px;
        min-width: 220px;
    }
    
    .oc-status-badge {
        font-size: 11px;
        font-weight: 600;
        padding: 4px 8px;
        border-radius: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .oc-status-badge.status-active {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    /* 🎯 v1.3.0: Status pentru renewal system */
    .oc-status-badge.status-pending {
        background: #cce5ff;
        color: #004085;
        border: 1px solid #b8daff;
    }
    
    .oc-status-badge.status-expired,
    .oc-status-badge.status-inactive {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .oc-status-badge.status-unknown {
        background: #e2e3e5;
        color: #6c757d;
        border: 1px solid #d1d3d4;
    }

    .oc-pending-activation-notice {
        padding: 12px 20px 0;
    }

    .oc-pending-notice-inner {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        border: 1px solid #b8daff;
        border-radius: 8px;
        background: #eef7ff;
        color: #0f3d62;
        flex-wrap: wrap;
    }

    .oc-pending-icon {
        flex: 0 0 auto;
        color: #0073aa;
        width: 18px;
        height: 18px;
        font-size: 18px;
        line-height: 18px;
    }

    .oc-pending-content {
        display: flex;
        align-items: center;
        gap: 8px;
        flex: 1 1 220px;
        min-width: 0;
        flex-wrap: wrap;
    }

    .oc-pending-title {
        margin: 0;
        font-size: 13px;
        line-height: 1.3;
    }

    .oc-pending-text {
        font-size: 13px;
        line-height: 1.4;
    }

    @media (max-width: 768px) {
        .oc-pending-activation-notice {
            padding: 10px 14px 0;
        }

        .oc-pending-notice-inner {
            align-items: flex-start;
        }

        .oc-pending-content {
            gap: 6px;
        }
    }
    
    .oc-action-buttons {
        display: flex;
        gap: 8px;
    }
    
    .oc-btn-info {
        background: #17a2b8;
        color: white;
        border-color: #17a2b8;
        font-size: 12px;
        padding: 6px 10px;
    }
    
    .oc-btn-info:hover {
        background: #138496;
        border-color: #138496;
    }
    
    .oc-btn-validate {
        background: #28a745;
        color: white;
        border-color: #28a745;
        font-size: 12px;
        padding: 6px 10px;
    }
    
    .oc-btn-validate:hover {
        background: #218838;
        border-color: #218838;
    }
    
    .oc-btn-info .dashicons,
    .oc-btn-validate .dashicons {
        font-size: 14px;
        width: 14px;
        height: 14px;
    }
    
    /* Expandable Details Section */
    .oc-card-details {
        padding: 0;
        background: #fff;
        overflow: hidden;
    }
    
    /* Admin Form Layout */
    .oc-admin-form {
        padding: 20px;
    }
    
    .oc-form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 16px;
    }
    
    .oc-form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    
    .oc-form-group.oc-full-width {
        grid-column: 1 / -1;
        position: relative;
    }
    
    .oc-form-group.oc-price-group {
        position: relative;
    }
    
    .oc-form-group label {
        font-size: 12px;
        font-weight: 600;
        color: #495057;
        margin-bottom: 4px;
    }
    
    .oc-form-group input:not([type='checkbox']):not([type='radio']),
    .oc-form-group select {
        padding: 8px 12px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        font-size: 14px;
        background: #fff;
        transition: all 0.2s ease;
    }
    
    .oc-form-group input:not([type='checkbox']):not([type='radio']):focus,
    .oc-form-group select:focus {
        outline: none;
        border-color: #007cba;
        box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.1);
    }

    .oc-no-expiry-label {
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px !important;
        margin-top: 8px !important;
        font-size: 13px !important;
        font-weight: 500 !important;
        color: #495057 !important;
        cursor: pointer !important;
        line-height: 1.25 !important;
        white-space: nowrap !important;
    }

    .oc-no-expiry-text {
        font-size: 13px !important;
        font-weight: 500 !important;
        color: #495057 !important;
    }

    .oc-no-expiry-checkbox {
        width: 14px !important;
        height: 14px !important;
        min-width: 14px !important;
        min-height: 14px !important;
        max-width: 14px !important;
        max-height: 14px !important;
        flex: 0 0 14px !important;
        margin: 0 !important;
        padding: 0 !important;
        border: 1px solid #8c8f94 !important;
        border-radius: 3px !important;
        background: #fff !important;
        vertical-align: middle !important;
        transform: none !important;
        appearance: checkbox !important;
        -webkit-appearance: checkbox !important;
    }

    .oc-card-details .oc-form-group .oc-no-expiry-label .oc-no-expiry-checkbox {
        width: 14px !important;
        height: 14px !important;
        min-width: 14px !important;
        min-height: 14px !important;
        max-width: 14px !important;
        max-height: 14px !important;
        transform: none !important;
    }

    .oc-no-expiry-checkbox[disabled] {
        opacity: 0.85 !important;
    }

    .oc-expiry-controls {
        display: flex !important;
        flex-direction: column !important;
        gap: 8px !important;
        margin-top: 8px !important;
    }

    .oc-card-details .oc-expiry-mode-wrap {
        margin-top: 0 !important;
        padding: 10px 12px !important;
        background: #f8f9fb !important;
        border: 1px solid #d9e2ef !important;
        border-radius: 8px !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }

    .oc-card-details .oc-expiry-mode-label {
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px !important;
        font-size: 13px !important;
        font-weight: 600 !important;
        color: #2d3748 !important;
        cursor: pointer !important;
        line-height: 1.3 !important;
    }

    .oc-card-details .oc-preserve-expiry-checkbox {
        width: 14px !important;
        height: 14px !important;
        min-width: 14px !important;
        min-height: 14px !important;
        margin: 0 !important;
        transform: none !important;
        vertical-align: middle !important;
        accent-color: #2271b1 !important;
    }

    .oc-card-details .oc-preserve-expiry-checkbox[disabled] {
        opacity: 0.85 !important;
        cursor: not-allowed !important;
    }

    .oc-card-details .oc-expiry-mode-text {
        font-size: 13px !important;
        font-weight: 600 !important;
        color: #1f2937 !important;
    }

    .oc-card-details .oc-expiry-mode-hint {
        display: block !important;
        margin-top: 6px !important;
        font-size: 12px !important;
        color: #5f6b7a !important;
        line-height: 1.4 !important;
    }

    .oc-renew-form .oc-form-grid,
    .oc-add-client-form .oc-form-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 14px !important;
        width: 100% !important;
        min-width: 0 !important;
    }

    .oc-renew-form .oc-form-field,
    .oc-add-client-form .oc-form-field,
    .oc-renew-form .oc-form-field.oc-field-full,
    .oc-add-client-form .oc-form-field.oc-field-full {
        min-width: 0 !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }

    .oc-renew-form .select_container,
    .oc-add-client-form .select_container {
        width: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }

    .oc-renew-form .select_container select,
    .oc-add-client-form .select_container select,
    .oc-renew-form .oc-form-field select,
    .oc-add-client-form .oc-form-field select {
        width: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }

    .oc-renew-course-selections,
    #oc-new-course-selections {
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)) !important;
        gap: 10px !important;
        overflow-x: hidden !important;
        width: 100% !important;
        min-width: 0 !important;
        box-sizing: border-box !important;
    }

    .oc-renew-course-selections .oc-course-checkbox,
    #oc-new-course-selections .oc-course-checkbox {
        display: grid !important;
        grid-template-columns: 20px minmax(0, 1fr) !important;
        grid-template-rows: auto auto !important;
        column-gap: 12px !important;
        row-gap: 4px !important;
        align-items: start !important;
        width: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;
        overflow: visible !important;
        box-sizing: border-box !important;
        margin: 0 !important;
        padding: 10px 12px !important;
        min-height: 62px !important;
        height: auto !important;
        border: 1px solid #dbe3ed !important;
        border-radius: 10px !important;
        background: #ffffff !important;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease !important;
    }

    .oc-renew-course-selections .oc-course-checkbox:hover,
    #oc-new-course-selections .oc-course-checkbox:hover,
    .oc-renew-course-selections .oc-course-checkbox:focus-within,
    #oc-new-course-selections .oc-course-checkbox:focus-within {
        border-color: #9ec5f8 !important;
        background: #f8fbff !important;
        box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.12) !important;
        transform: none !important;
    }

    .oc-renew-course-selections .oc-course-checkbox input[type=\"checkbox\"],
    #oc-new-course-selections .oc-course-checkbox input[type=\"checkbox\"] {
        grid-column: 1 !important;
        grid-row: 1 / span 2 !important;
        width: 16px !important;
        height: 16px !important;
        min-width: 16px !important;
        min-height: 16px !important;
        max-width: 16px !important;
        max-height: 16px !important;
        margin: 2px 0 0 0 !important;
        padding: 0 !important;
        border: 1px solid #8c8f94 !important;
        border-radius: 3px !important;
        background: #fff !important;
        accent-color: #1a73e8 !important;
        transform: none !important;
        box-sizing: border-box !important;
        align-self: start !important;
        justify-self: start !important;
        -webkit-appearance: checkbox !important;
        appearance: checkbox !important;
    }

    .oc-form-field.oc-field-full .oc-send-email-label {
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 10px 12px !important;
        border: 1px solid #d8dee6 !important;
        border-radius: 8px !important;
        background: #f8fafc !important;
        color: #2f3b4a !important;
        font-size: 13px !important;
        font-weight: 600 !important;
        line-height: 1.35 !important;
        box-sizing: border-box !important;
        cursor: pointer !important;
    }

    .oc-form-field.oc-field-full .oc-send-email-label .oc-send-email-checkbox {
        width: 16px !important;
        height: 16px !important;
        min-width: 16px !important;
        min-height: 16px !important;
        margin: 0 !important;
        padding: 0 !important;
        transform: none !important;
        vertical-align: middle !important;
        -webkit-appearance: checkbox !important;
        appearance: checkbox !important;
    }

    .oc-renew-course-selections .oc-course-checkbox .oc-course-name,
    #oc-new-course-selections .oc-course-checkbox .oc-course-name {
        display: block !important;
        grid-column: 2 !important;
        grid-row: 1 !important;
        width: auto !important;
        max-width: 100% !important;
        overflow: visible !important;
        text-overflow: clip !important;
        white-space: normal !important;
        word-break: break-word !important;
        color: #1f2937 !important;
        font-size: 13px !important;
        font-weight: 600 !important;
        line-height: 1.35 !important;
        text-align: left !important;
        min-height: 18px !important;
        height: auto !important;
        align-self: start !important;
    }

    .oc-renew-course-selections .oc-course-checkbox .oc-course-id,
    #oc-new-course-selections .oc-course-checkbox .oc-course-id {
        display: block !important;
        grid-column: 2 !important;
        grid-row: 2 !important;
        font-size: 12px !important;
        color: #6b7280 !important;
        line-height: 1.25 !important;
        overflow-wrap: anywhere !important;
        word-break: break-word !important;
        text-align: left !important;
        min-height: 16px !important;
        height: auto !important;
        align-self: start !important;
    }

    @media (max-width: 900px) {
        .oc-renew-form .oc-form-grid,
        .oc-add-client-form .oc-form-grid {
            grid-template-columns: 1fr !important;
        }

        .oc-renew-course-selections,
        #oc-new-course-selections {
            grid-template-columns: 1fr !important;
        }

        .oc-renew-course-selections .oc-course-checkbox,
        #oc-new-course-selections .oc-course-checkbox {
            position: relative !important;
            display: block !important;
            padding: 10px !important;
            padding-left: 34px !important;
            min-height: 74px !important;
            height: auto !important;
            overflow: visible !important;
        }

        .oc-renew-course-selections .oc-course-checkbox input[type=\"checkbox\"],
        #oc-new-course-selections .oc-course-checkbox input[type=\"checkbox\"] {
            position: absolute !important;
            left: 10px !important;
            top: 11px !important;
            width: 16px !important;
            height: 16px !important;
            min-width: 16px !important;
            min-height: 16px !important;
            margin: 0 !important;
            float: none !important;
            align-self: flex-start !important;
        }

        .oc-renew-course-selections .oc-course-checkbox .oc-course-name,
        #oc-new-course-selections .oc-course-checkbox .oc-course-name {
            display: block !important;
            width: 100% !important;
            min-width: 0 !important;
            max-width: 100% !important;
            white-space: normal !important;
            overflow: visible !important;
            text-overflow: clip !important;
            word-break: break-word !important;
            overflow-wrap: anywhere !important;
            margin-top: 0 !important;
            box-sizing: border-box !important;
        }

        .oc-renew-course-selections .oc-course-checkbox .oc-course-id,
        #oc-new-course-selections .oc-course-checkbox .oc-course-id {
            display: block !important;
            width: 100% !important;
            min-width: 0 !important;
            max-width: 100% !important;
            white-space: normal !important;
            overflow: visible !important;
            text-overflow: clip !important;
            word-break: break-word !important;
            overflow-wrap: anywhere !important;
            margin-top: 1px !important;
            box-sizing: border-box !important;
        }
    }

    .oc-qr-modal-overlay {
        position: fixed !important;
        inset: 0 !important;
        z-index: 99999 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 20px !important;
        background: rgba(17, 24, 39, 0.72) !important;
        backdrop-filter: blur(2px) !important;
    }

    .oc-qr-modal-card {
        width: min(440px, 100%) !important;
        max-height: min(88vh, 680px) !important;
        overflow-y: auto !important;
        background: #ffffff !important;
        border-radius: 18px !important;
        padding: 22px !important;
        box-shadow: 0 28px 70px rgba(15, 23, 42, 0.28) !important;
        border: 1px solid #dbe4ef !important;
        box-sizing: border-box !important;
    }

    .oc-qr-modal-head {
        display: flex !important;
        align-items: flex-start !important;
        justify-content: space-between !important;
        gap: 16px !important;
        margin-bottom: 16px !important;
    }

    .oc-qr-modal-heading {
        min-width: 0 !important;
        flex: 1 1 auto !important;
    }

    .oc-qr-modal-kicker {
        display: inline-flex !important;
        align-items: center !important;
        padding: 5px 10px !important;
        border-radius: 999px !important;
        background: #e8f1ff !important;
        color: #1858b8 !important;
        font-size: 11px !important;
        font-weight: 700 !important;
        letter-spacing: 0.08em !important;
        text-transform: uppercase !important;
        margin-bottom: 8px !important;
    }

    .oc-qr-modal-title {
        margin: 0 !important;
        font-size: 24px !important;
        line-height: 1.3 !important;
        font-weight: 700 !important;
        color: #0f172a !important;
        text-align: left !important;
        word-break: break-word !important;
    }

    .oc-qr-modal-icon-close {
        width: 40px !important;
        height: 40px !important;
        border-radius: 999px !important;
        border: 1px solid #d7e0ea !important;
        background: #f8fafc !important;
        color: #334155 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        flex: 0 0 auto !important;
        cursor: pointer !important;
        transition: background 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease !important;
    }

    .oc-qr-modal-icon-close:hover {
        background: #eef4fb !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.10) !important;
    }

    .oc-qr-modal-icon-close .dashicons {
        width: 18px !important;
        height: 18px !important;
        font-size: 18px !important;
    }

    .oc-qr-grid {
        display: grid !important;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)) !important;
        gap: 14px !important;
        margin-bottom: 18px !important;
    }

    .oc-qr-item {
        background: #f8fafc !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 12px !important;
        padding: 14px !important;
        text-align: center !important;
    }

    .oc-qr-image-wrap {
        border-radius: 10px !important;
        background: #ffffff !important;
        padding: 10px !important;
        box-shadow: inset 0 0 0 1px #eef2f7 !important;
    }

    .oc-qr-image {
        display: block !important;
        width: min(280px, 100%) !important;
        height: auto !important;
        margin: 0 auto !important;
        border-radius: 8px !important;
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.12) !important;
    }

    .oc-qr-item-label {
        margin-top: 10px !important;
        font-size: 13px !important;
        font-weight: 600 !important;
        color: #4b5563 !important;
    }

    .oc-qr-modal-actions {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 12px !important;
        flex-wrap: wrap !important;
        width: 100% !important;
        box-sizing: border-box !important;
        margin-top: 18px !important;
        padding-top: 18px !important;
        border-top: 1px solid #e5e7eb !important;
    }

    .oc-qr-btn {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        min-width: 190px !important;
        min-height: 46px !important;
        height: auto !important;
        padding: 11px 18px !important;
        border-radius: 10px !important;
        border: 1px solid transparent !important;
        font-size: 14px !important;
        font-weight: 700 !important;
        line-height: 1.25 !important;
        letter-spacing: 0.1px !important;
        text-align: center !important;
        white-space: normal !important;
        word-break: break-word !important;
        overflow: visible !important;
        text-overflow: clip !important;
        font-family: inherit !important;
        text-decoration: none !important;
        cursor: pointer !important;
        -webkit-appearance: none !important;
        appearance: none !important;
        box-sizing: border-box !important;
        transition: transform 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease !important;
    }

    .oc-qr-btn .dashicons {
        width: 16px !important;
        height: 16px !important;
        font-size: 16px !important;
        margin-right: 8px !important;
    }

    .oc-qr-btn:hover {
        transform: translateY(-1px) !important;
    }

    .oc-qr-btn-download {
        background: #1a73e8 !important;
        color: #ffffff !important;
        box-shadow: 0 8px 18px rgba(26, 115, 232, 0.28) !important;
    }

    .oc-qr-btn-close {
        background: #eef2f7 !important;
        color: #111827 !important;
        border-color: #cfd8e3 !important;
        box-shadow: 0 8px 18px rgba(71, 85, 105, 0.18) !important;
    }

    @media (max-width: 640px) {
        .oc-qr-modal-overlay {
            padding: 10px !important;
        }

        .oc-qr-modal-card {
            width: 100% !important;
            max-height: 92vh !important;
            padding: 16px !important;
            border-radius: 12px !important;
        }

        .oc-qr-modal-head {
            align-items: center !important;
            gap: 12px !important;
            margin-bottom: 12px !important;
        }

        .oc-qr-modal-title {
            font-size: 20px !important;
            line-height: 1.3 !important;
        }

        .oc-qr-grid {
            grid-template-columns: 1fr !important;
            gap: 10px !important;
        }

        .oc-qr-modal-actions {
            display: grid !important;
            grid-template-columns: 1fr !important;
            justify-items: stretch !important;
            align-items: stretch !important;
            gap: 10px !important;
            position: static !important;
            background: #ffffff !important;
            padding-top: 10px !important;
            padding-bottom: 2px !important;
            margin-top: 4px !important;
            border-top: 1px solid #e5e7eb !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }

        .oc-qr-btn {
            width: 100% !important;
            min-width: 0 !important;
            max-width: 100% !important;
            min-height: 48px !important;
            font-size: 14px !important;
            border-radius: 12px !important;
            padding: 12px 14px !important;
            margin: 0 !important;
            box-sizing: border-box !important;
        }

        .oc-qr-btn-close {
            display: inline-flex !important;
            justify-content: center !important;
            align-items: center !important;
        }

        .oc-qr-modal-icon-close {
            width: 36px !important;
            height: 36px !important;
        }

        .oc-qr-item-label {
            font-size: 14px !important;
        }
    }

    .oc-validation-modal {
        position: fixed !important;
        inset: 0 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 24px !important;
        background: rgba(15, 23, 42, 0.62) !important;
        backdrop-filter: blur(4px) !important;
        z-index: 100000 !important;
    }

    .oc-validation-modal-content {
        width: min(520px, 100%) !important;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%) !important;
        border: 1px solid #dbe4ef !important;
        border-radius: 18px !important;
        box-shadow: 0 28px 70px rgba(15, 23, 42, 0.26) !important;
        padding: 28px !important;
        text-align: center !important;
        animation: slideIn 0.22s ease-out !important;
        box-sizing: border-box !important;
        max-width: calc(100vw - 48px) !important;
        max-height: calc(100vh - 48px) !important;
        overflow-y: auto !important;
        overflow-x: hidden !important;
    }

    .oc-validation-modal-content.success {
        box-shadow: 0 28px 70px rgba(15, 23, 42, 0.24), inset 0 4px 0 #16a34a !important;
    }

    .oc-validation-modal-content.error {
        box-shadow: 0 28px 70px rgba(15, 23, 42, 0.24), inset 0 4px 0 #dc2626 !important;
    }

    .oc-validation-modal-content.loading,
    .oc-validation-modal-content.oc-validation-modal-content-gateway {
        box-shadow: 0 28px 70px rgba(15, 23, 42, 0.24), inset 0 4px 0 #1d4ed8 !important;
    }

    .oc-validation-modal-badge {
        width: 72px !important;
        height: 72px !important;
        border-radius: 22px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        margin: 0 auto 18px !important;
        background: linear-gradient(135deg, #eef5ff 0%, #dcecff 100%) !important;
        color: #1858b8 !important;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.65) !important;
    }

    .oc-validation-modal-content.success .oc-validation-modal-badge {
        background: linear-gradient(135deg, #eaf8ef 0%, #d8f3df 100%) !important;
        color: #15803d !important;
    }

    .oc-validation-modal-content.error .oc-validation-modal-badge {
        background: linear-gradient(135deg, #fff1f2 0%, #ffe1e5 100%) !important;
        color: #c62828 !important;
    }

    .oc-validation-modal-badge .dashicons {
        width: 32px !important;
        height: 32px !important;
        font-size: 32px !important;
    }

    .oc-validation-title {
        margin: 0 0 12px !important;
        font-size: 24px !important;
        line-height: 1.25 !important;
        color: #0f172a !important;
        font-weight: 700 !important;
    }

    .oc-validation-message {
        font-size: 16px !important;
        line-height: 1.6 !important;
        color: #334155 !important;
        margin: 0 !important;
    }

    .oc-validation-actions {
        display: flex !important;
        justify-content: center !important;
        flex-wrap: wrap !important;
        gap: 10px !important;
        margin-top: 18px !important;
    }

    .oc-validation-close,
    .oc-validation-yes,
    .oc-validation-no,
    .oc-validation-actions .button {
        min-height: 44px !important;
        padding: 0 18px !important;
        border-radius: 10px !important;
        font-size: 14px !important;
        font-weight: 700 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        box-shadow: none !important;
    }

    .oc-validation-no.button,
    .oc-validation-actions .button:not(.button-primary) {
        background: #eef2f7 !important;
        color: #0f172a !important;
        border-color: #cfd8e3 !important;
    }

    .oc-validation-modal-content.loading .oc-validation-message {
        margin-top: 4px !important;
    }

    .oc-validation-modal-content.loading .oc-spinner {
        margin: 0 !important;
    }

    @media (max-width: 640px) {
        .oc-qr-modal-overlay {
            align-items: center !important;
            justify-content: center !important;
            padding: 8px !important;
            overflow-y: auto !important;
            -webkit-overflow-scrolling: touch !important;
        }

        .oc-validation-modal {
            padding: 8px !important;
            align-items: center !important;
            justify-content: center !important;
            overflow-y: auto !important;
            -webkit-overflow-scrolling: touch !important;
        }

        .oc-validation-modal-content {
            width: min(420px, 82vw) !important;
            max-width: min(420px, 82vw) !important;
            max-height: calc(100vh - 16px) !important;
            padding: 18px 14px !important;
            border-radius: 14px !important;
            margin: auto !important;
        }

        .oc-qr-modal-card {
            width: min(420px, 82vw) !important;
            max-width: min(420px, 82vw) !important;
            max-height: calc(100vh - 16px) !important;
            padding: 14px !important;
            margin: auto !important;
        }

        .oc-validation-modal-badge {
            width: 62px !important;
            height: 62px !important;
            border-radius: 18px !important;
            margin-bottom: 14px !important;
        }

        .oc-validation-title {
            font-size: 20px !important;
        }

        .oc-validation-message {
            font-size: 15px !important;
        }

        .oc-validation-actions {
            display: grid !important;
            grid-template-columns: 1fr !important;
        }

        .oc-validation-close,
        .oc-validation-yes,
        .oc-validation-no,
        .oc-validation-actions .button {
            width: 100% !important;
        }
    }
    
    /* Readonly vs Editable Fields */
    .oc-field-readonly {
        background-color: #f8f9fa !important;
        color: #6c757d !important;
        cursor: not-allowed;
    }
    
    .oc-field-editable {
        background-color: #fff !important;
        color: #495057 !important;
        border-color: #007cba !important;
    }
    
    /* Special Displays */
    .oc-price-display {
        padding: 8px 12px;
        background: #f8f9fa;
        border: 1px solid #ced4da;
        border-radius: 4px;
        font-weight: 600;
        color: #28a745;
    }
    
    .oc-date-display {
        padding: 8px 12px;
        background: #f8f9fa;
        border: 1px solid #ced4da;
        border-radius: 4px;
        color: #495057;
    }
    
    /* Action Buttons */
    .oc-price-btn, .oc-courses-btn {
        position: absolute;
        right: 8px;
        top: 32px;
        width: 28px;
        height: 28px;
        border: 1px solid #007cba;
        background: #007cba;
        color: white;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .oc-price-btn:hover, .oc-courses-btn:hover {
        background: #0056b3;
    }
    
    /* Form Actions */
    .oc-form-actions {
        display: flex;
        gap: 12px;
        padding-top: 16px;
        border-top: 1px solid #e9ecef;
        justify-content: flex-end;
    }
    
    .oc-btn {
        padding: 8px 16px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .oc-btn-primary {
        background: #007cba;
        color: white;
        border-color: #007cba;
    }
    
    .oc-btn-primary:hover {
        background: #0056b3;
        border-color: #0056b3;
    }
    
    .oc-btn-secondary {
        background: #6c757d;
        color: white;
        border-color: #6c757d;
    }
    
    .oc-btn-secondary:hover {
        background: #545b62;
        border-color: #545b62;
    }
    
    .oc-bulk-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        background: #f8f9fa;
        border-top: 1px solid #e1e1e1;
    }
    
    .oc-bulk-actions-right {
        display: flex;
        gap: 10px;
    }
    
    /* Responsive Mobile */
    @media (max-width: 768px) {
        .oc-card-header {
            flex-direction: column;
            gap: 12px;
            padding: 14px 16px;
        }
        
        .oc-card-main-info {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .oc-membership-info {
            grid-template-columns: 1fr;
            gap: 6px;
        }

        .oc-card-actions {
            flex: 0 0 auto;
            min-width: 100%;
            align-items: stretch;
        }
        
        .oc-form-row {
            grid-template-columns: 1fr;
            gap: 12px;
        }
    }
    </style>";
}
