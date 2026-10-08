<?php
namespace IAAI\Portal;
defined('ABSPATH') || exit;
final class Language {
    public static function message(string $value): string {
        if (Core::locale() !== 'hi') { return $value; }
        $map = [
            'Enter your name, a valid email and matching passwords of at least 12 characters. Accept the account notice.' => 'नाम, सही ईमेल और कम से कम 12 अक्षरों का एक जैसा पासवर्ड भरें। अकाउंट नोटिस स्वीकार करें।',
            'The sign-in details could not be verified.' => 'लॉग इन विवरण सत्यापित नहीं हुए।',
            'Staff must use the MFA-protected WordPress sign-in.' => 'टीम के सदस्य MFA से सुरक्षित WordPress लॉग इन इस्तेमाल करें।',
            'This confirmation link is invalid, expired or already used.' => 'यह पुष्टि लिंक अमान्य है, इसकी अवधि समाप्त है या इसका उपयोग हो चुका है।',
            'Email verified. You can now sign in.' => 'ईमेल सत्यापित है। अब लॉग इन कर सकते हैं।',
            'If verification is needed, a confirmation message has been queued.' => 'सत्यापन जरूरी होने पर पुष्टि संदेश भेजने के लिए कतार में रखा गया है।',
            'If this address can be registered, a verification message has been queued. Check your email or request another verification message.' => 'यह ईमेल रजिस्टर हो सकता है तो सत्यापन संदेश कतार में रखा गया है। ईमेल देखें या सत्यापन फिर मांगें।',
            'If a matching account exists, password recovery will be attempted. If you do not receive a message, retry later or contact the verified support channel.' => 'अकाउंट मौजूद है तो पासवर्ड रिकवरी का प्रयास होगा। संदेश न मिले तो बाद में फिर प्रयास करें या सत्यापित सहायता संपर्क इस्तेमाल करें।',
            'The reset link is invalid, expired or already used.' => 'रीसेट लिंक अमान्य है, इसकी अवधि समाप्त है या इसका उपयोग हो चुका है।',
            'Password updated. Sign in with your new password.' => 'पासवर्ड अपडेट हुआ। नए पासवर्ड से लॉग इन करें।',
            'Re-enter your password to confirm this action.' => 'इस बदलाव की पुष्टि के लिए वर्तमान पासवर्ड दोबारा भरें।',
            'Confirmation has been queued for the new address. Your current email stays active.' => 'नए ईमेल पर पुष्टि संदेश कतार में रखा गया है। पुष्टि तक वर्तमान ईमेल चालू रहेगा।',
            'New storage and sharing are disabled. Existing records can still be exported or deleted.' => 'नया स्टोरेज और sharing बंद है। मौजूदा रिकॉर्ड एक्सपोर्ट या हटाए जा सकते हैं।',
            'If this enquiry has a verified email contact, linking instructions will be attempted at its saved address.' => 'Enquiry में ईमेल सेव है तो उसी ईमेल पर जोड़ने के निर्देश भेजने का प्रयास होगा।',
            'A valid submission reference is required.' => 'सही submission reference जरूरी है।',
            'Upload one to five files.' => 'एक से पाँच फ़ाइलें अपलोड करें।',
            'Each readable file must be 10 MB or smaller.' => 'हर पढ़ने योग्य फ़ाइल 10 MB या उससे छोटी हो।',
            'Only PDF, JPG and PNG files with matching content are accepted.' => 'केवल सही सामग्री वाली PDF, JPG और PNG फ़ाइलें स्वीकार होती हैं।',
            'Your account storage quota would be exceeded.' => 'अकाउंट की स्टोरेज सीमा पार हो जाएगी।',
            'Private storage, encryption, scanning, PDF tools and an approved retention policy are required before uploads.' => 'अपलोड से पहले निजी स्टोरेज, encryption, स्कैनर, PDF tools और स्वीकृत रिटेंशन नीति जरूरी है।',
            'Use the configured MFA-protected staff sign-in.' => 'MFA से सुरक्षित टीम लॉग इन इस्तेमाल करें।',
        ];
        return $map[$value] ?? $value;
    }
    public static function response($value) {
        if ($value instanceof \WP_REST_Response) { $value->set_data(self::response($value->get_data())); }
        elseif (is_array($value) && isset($value['message']) && is_string($value['message'])) { $value['message'] = self::message($value['message']); }
        return $value;
    }
}
