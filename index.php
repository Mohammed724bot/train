أهلاً بك مجدداً! لقد قمت بتطوير كود بوت ترين وإضافة لعبة تفكيك الكلمات التي طلبتها.
فكرة اللعبة الجديدة:
 * يقترح البوت كلمة (مبعثرة الأحرف) أو يطلب تفكيك الكلمة.
 * يمنح المشاركين 30 ثانية لحل وتفكيك الكلمة وإرسالها بشكلها الصحيح.
 * أول شخص يجيب بشكل صحيح ضمن الوقت المخصص يحسب له نقطة.
 * من يجمع 10 نقاط أولاً يفوز بالمسابقة ويتم الاحتفال بفوزه!
النسخة المحدثة من الكود (مع لعبة التفكيك ونظام النقاط):
<?php
// إعدادات الرمز السري ورمز الوصول الخاص بإنستجرام
$VERIFY_TOKEN = "train_bot_secret_2026";
$ACCESS_TOKEN = "EAAPgpzgAZBvkBS1UsXSxgF7PjURZABupLd37PMyM22kiTSAkNFocNURU5GjZAzg6SR0mM5QcT1OTxa1vAoKa1PZBltcYlIriEmq3iS5pDgHq5IByjcJbFAdqE4MGx5uVt15X8dzI0H5ujq7raMjvdMpfFWk9MgZABBLT9lFBs2YF7hvZAjRqtT3IHZAwazSuVzduEw2CXLGpJVKqBZBnTgH5f0jtWSo6YudSTjuDx04vmTxlUvph1ZABk8PjQe6dfXfaIZAozZCztY34rzuLPAIShgb";
$APP_SECRET   = "1ed40e69a7e122c7eecc19f7aae337c4"; // أضف App Secret للتحقق الأمني

// ملف بسيط لتخزين نقاط ومراحل اللاعبين
$db_file = 'game_sessions.json';
function loadSessions() {
    global $db_file;
    if (file_exists($db_file)) {
        return json_decode(file_get_contents($db_file), true) ?? [];
    }
    return [];
}
function saveSessions($data) {
    global $db_file;
    file_put_contents($db_file, json_encode($data, JSON_UNESCAPED_UNICODE));
}

// 1. مرحلة التحقق من الربط (Webhook Verification مع ميتا)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';

    if ($mode === 'subscribe' && $token === $VERIFY_TOKEN) {
        echo $challenge;
        http_response_code(200);
        exit;
    } else {
        http_response_code(403);
        exit;
    }
}

// 2. مرحلة استقبال الرسائل والأحداث (POST Requests)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_post_data = file_get_contents('php://input');
    
    // التحقق من توقيع طلب ميتا للأمان
    $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    if (!empty($APP_SECRET) && $signature) {
        list($algo, $hash) = explode('=', $signature, 2);
        $expected_hash = hash_hmac($algo, $raw_post_data, $APP_SECRET);
        if (!hash_equals($expected_hash, $hash)) {
            http_response_code(403);
            exit('Invalid signature');
        }
    }

    $input = json_decode($raw_post_data, true);

    // التأكد من أن الحدث قادم من مراسلات إنستجرام
    if (isset($input['object']) && $input['object'] === 'instagram') {
        foreach ($input['entry'] as $entry) {
            $messaging_events = $entry['messaging'] ?? [];
            
            foreach ($messaging_events as $event) {
                $sender_id = $event['sender']['id'] ?? null;
                
                if (isset($event['message']['text']) && $sender_id) {
                    $message_text = trim($event['message']['text']);
                    $message_lower = mb_strtolower($message_text);
                    
                    $sessions = loadSessions();
                    // تهيئة بيانات المستخدم إذا كانت أول مرة
                    if (!isset($sessions[$sender_id])) {
                        $sessions[$sender_id] = [
                            'score' => 0,
                            'state' => 'idle',
                            'target_word' => '',
                            'start_time' => 0
                        ];
                    }
                    
                    $user = &$sessions[$sender_id];
                    
                    // الرد عند مناداة اسم "ترين" أو طلب قائمة الألعاب
                    if (str_contains($message_lower, 'ترين') || str_contains($message_lower, 'train') || $message_lower === 'ألعاب' || $message_lower === 'العاب') {
                        $reply_message = "أهلاً بك! أنا **ترين** 🤖✨.\nإليك الألعاب المتاحة:\n\n🧩 أكتب **تفكك** - لبدء لعبة تفكيك الكلمات (من يجمع 10 نقاط يفوز!)\n⚡ أكتب **سرعة** - لتحدي سرعة الكتابة\n\nاختر لعبتك لنبدأ فوراً!";
                        sendInstagramMessage($sender_id, $reply_message, $ACCESS_TOKEN);
                    } 
                    // بدء لعبة التفكيك
                    elseif ($message_lower === 'تفكك' || $message_lower === 'تفكيك') {
                        // قائمة الكلمات مع طريقة تفكيكها (حروف مبعثرة)
                        $word_bank = [
                            ['word' => 'برمجة', 'scrambled' => 'ج م ب ر ة'],
                            ['word' => 'حاسوب', 'scrambled' => 'ب و س ح ا'],
                            ['word' => 'تاريخ', 'scrambled' => 'خ ر ت ا ي'],
                            ['word' => 'مهندس', 'scrambled' => 'س د ه م ن'],
                            ['word' => 'إنستجرام', 'scrambled' => 'ر ج ت ا ن س إ م']
                        ];
                        
                        $selected = $word_bank[array_rand($word_bank)];
                        
                        $user['state'] = 'decrypt_game';
                        $user['target_word'] = $selected['word'];
                        $user['start_time'] = time();
                        saveSessions($sessions);
                        
                        $reply_message = "🧩 **تحدي تفكيك الكلمات مع ترين!**\nقم بتركيب وحل الحروف التالية لتكتب الكلمة الصحيحة:\n\n🔤 **{$selected['scrambled']}**\n\n*(لديك 30 ثانية فقط للإجابة أولاً وإحراز نقطة! رصيدك الحالي: {$user['score']}/10)*";
                        sendInstagramMessage($sender_id, $reply_message, $ACCESS_TOKEN);
                    } 
                    // التحقق أثناء اللعب في لعبة التفكيك
                    elseif ($user['state'] === 'decrypt_game') {
                        $time_elapsed = time() - $user['start_time'];
                        
                        if ($time_elapsed > 30) {
                            $reply_message = "⏳ انتهى الوقت (أكثر من 30 ثانية)! الكلمة الصحيحة كانت: **{$user['target_word']}**. اكتب (تفكك) للمحاولة في كلمة جديدة.";
                            $user['state'] = 'idle';
                            saveSessions($sessions);
                        } elseif ($message_text === $user['target_word']) {
                            $user['score']++;
                            $current_score = $user['score'];
                            
                            if ($current_score >= 10) {
                                $reply_message = "🏆 **تهانينا الكبرى!** لقد وصلت إلى 10 نقاط وفزت بمسابقة تفكيك الكلمات مع ترين! 🎉✨\nاكتب (تفكك) إذا أردت بدء تحدٍ جديد.";
                                $user['score'] = 0; // إعادة التعيين بعد الفوز
                            } else {
                                $reply_message = "🎉 كفو! فككت الكلمة في الوقت المناسب.\nأحرزت نقطة! 🎯 رصيدك الآن: **{$current_score}/10**.\n\nاكتب (تفكك) للسؤال التالي!";
                            }
                            $user['state'] = 'idle';
                            saveSessions($sessions);
                        } else {
                            $reply_message = "❌ إجابة غير خاطئة! حاول تحليل الحروف المبعثرة بشكل صحيح أو اكتب (تفكك) لكلمة جديدة.";
                        }
                        sendInstagramMessage($sender_id, $reply_message, $ACCESS_TOKEN);
                    } 
                    // لعبة السرعة السابقة
                    elseif ($message_lower === 'سرعة') {
                        $reply_message = "⚡ أكتب كلمة: **تحدي** (لديك 30 ثانية للإجابة)";
                        sendInstagramMessage($sender_id, $reply_message, $ACCESS_TOKEN);
                    }
                    else {
                        $reply_message = "معك **ترين**! 🤖 لم أتمكن من فهم رسالتك. اكتب كلمة **(ألعاب)** لتظهر لك قائمة التحديات المتاحة.";
                        sendInstagramMessage($sender_id, $reply_message, $ACCESS_TOKEN);
                    }
                }
            }
        }
        http_response_code(200);
        exit;
    }
}

// دالة إرسال الرسائل عبر Instagram Graph API
function sendInstagramMessage($recipient_id, $message_text, $access_token) {
    $url = "https://graph.facebook.com/v20.0/me/messages?access_token=" . $access_token;
    
    $data = [
        'recipient' => ['id' => $recipient_id],
        'message' => ['text' => $message_text]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return $response;
}
?>

