<?php
// ==========================================
// PROFESSIONAL KONSTRUKTOR BOT (Render Version)
// ==========================================

// --- SOZLAMALAR (Environment Variables orqali) ---
$api_key = getenv('BOT_TOKEN'); // Render'da BOT_TOKEN nomi bilan saqlanadi
$admin_id = getenv('ADMIN_ID');  // Render'da ADMIN_ID nomi bilan saqlanadi

// To'lov ma'lumotlarini ham o'zgaruvchi qilsa bo'ladi
$card_number = getenv('CARD_NUMBER') ?: "8600 0000 0000 0000"; 
$card_holder = getenv('CARD_HOLDER') ?: "Ism Familiya";

// Tekshirish: Agar o'zgaruvchilar bo'sh bo'lsa, xatolik chiqarmaslik uchun
if (!$api_key || !$admin_id) {
    error_log("Xatolik: BOT_TOKEN yoki ADMIN_ID sozlanmagan!");
    exit();
}

define('API_KEY', $api_key);
define('ADMIN_ID', $admin_id);

// Kerakli papkalarni yaratish
if (!file_exists('data')) mkdir('data');
if (!file_exists('data/users')) mkdir('data/users');

// --- MA'LUMOTLAR BAZASI FUNKSIYALARI (JSON) ---

function bot($method, $datas = []) {
    $url = "https://api.telegram.org/bot" . API_KEY . "/" . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
    $res = curl_exec($ch);
    if (curl_error($ch)) {
        var_dump(curl_error($ch));
    } else {
        return json_decode($res);
    }
}

function getUser($id) {
    $file = "data/users/$id.json";
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true);
    }
    return null;
}

function saveUser($id, $data) {
    file_put_contents("data/users/$id.json", json_encode($data, JSON_PRETTY_PRINT));
}

function getGlobalData($name) {
    $file = "data/$name.json";
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true);
    }
    return [];
}

function saveGlobalData($name, $data) {
    file_put_contents("data/$name.json", json_encode($data, JSON_PRETTY_PRINT));
}

// --- UPDATE NI QABUL QILISH ---
$update = json_decode(file_get_contents('php://input'));

if (isset($update->message)) {
    $message = $update->message;
    $chat_id = $message->chat->id;
    $text = isset($message->text) ? $message->text : "";
    $name = $message->from->first_name;
    $username = isset($message->from->username) ? $message->from->username : "nomalum";
    $type = $message->chat->type;
    $message_id = $message->message_id;
    $photo = isset($message->photo) ? $message->photo : null;
}

if (isset($update->callback_query)) {
    $callback = $update->callback_query;
    $chat_id = $callback->message->chat->id;
    $data = $callback->data;
    $message_id = $callback->message->message_id;
    $cb_id = $callback->id;
    $cb_user_id = $callback->from->id;
}

// --- FOYDALANUVCHINI ROIXATGA OLISH ---
$user = getUser($chat_id);
if (!$user && isset($chat_id)) {
    $user = [
        'id' => $chat_id,
        'balance' => 0,
        'step' => 'none',
        'ref_count' => 0,
        'invited_by' => null,
        'join_date' => date('Y-m-d H:i:s'),
        'last_bonus' => null
    ];
    
    // Referral tekshirish
    if (strpos($text, "/start") !== false) {
        $ex = explode(" ", $text);
        if (isset($ex[1]) && is_numeric($ex[1]) && $ex[1] != $chat_id) {
            $inviter = getUser($ex[1]);
            if ($inviter) {
                $user['invited_by'] = $ex[1];
                bot('sendMessage', [
                    'chat_id' => $ex[1],
                    'text' => "🔔 Sizda yangi referal! Balansingizga +200 so'm qo'shildi."
                ]);
                $inviter['balance'] += 200; // Referal summasi
                $inviter['ref_count'] += 1;
                saveUser($ex[1], $inviter);
            }
        }
    }
    saveUser($chat_id, $user);
}

// --- MENYULAR ---
$main_menu = json_encode([
    'keyboard' => [
        [['text' => "🤖 Bot ochish"], ['text' => "👤 Kabinet"]],
        [['text' => "💸 Pul ishlash"], ['text' => "💰 Hisobni to'ldirish"]],
        [['text' => "📞 Yordam"]]
    ],
    'resize_keyboard' => true
]);

$cancel_menu = json_encode([
    'keyboard' => [
        [['text' => "🚫 Bekor qilish"]]
    ],
    'resize_keyboard' => true
]);

$admin_menu = json_encode([
    'keyboard' => [
        [['text' => "📊 Statistika"], ['text' => "✉️ Xabar yuborish"]],
        [['text' => "➕ Bot qo'shish"], ['text' => "⚙️ Botlarni boshqarish"]],
        [['text' => "🔙 Chiqish"]]
    ],
    'resize_keyboard' => true
]);

// --- MANTIQ BOSHLANISHI ---

// 1. BUYRUQLAR
if ($text == "/start") {
    $user['step'] = 'none';
    saveUser($chat_id, $user);
    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "👋 Assalomu alaykum, <b>$name</b>!\n\nBizning professional konstruktor botimizga xush kelibsiz. Quyidagi menyudan kerakli bo'limni tanlang.",
        'parse_mode' => 'HTML',
        'reply_markup' => $main_menu
    ]);
    exit();
}

if ($text == "🚫 Bekor qilish") {
    $user['step'] = 'none';
    saveUser($chat_id, $user);
    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "❌ Amal bekor qilindi.",
        'reply_markup' => $main_menu
    ]);
    exit();
}

// 2. ADMIN PANEL
if ($text == "/admin" && $chat_id == ADMIN_ID) {
    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "👨‍💻 <b>Admin panelga xush kelibsiz!</b>",
        'parse_mode' => 'HTML',
        'reply_markup' => $admin_menu
    ]);
    exit();
}

if ($chat_id == ADMIN_ID) {
    if ($text == "🔙 Chiqish") {
        bot('sendMessage', ['chat_id' => $chat_id, 'text' => "Menyu:", 'reply_markup' => $main_menu]);
        exit();
    }
    
    if ($text == "📊 Statistika") {
        $users_count = count(glob("data/users/*.json"));
        $bots = getGlobalData('bots_list');
        $bots_count = count($bots);
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "📊 <b>Statistika:</b>\n\n👤 Foydalanuvchilar: <b>$users_count</b> ta\n🤖 Botlar soni: <b>$bots_count</b> ta",
            'parse_mode' => 'HTML'
        ]);
    }
    
    if ($text == "➕ Bot qo'shish") {
        $user['step'] = 'admin_add_bot_name';
        saveUser($chat_id, $user);
        bot('sendMessage', ['chat_id' => $chat_id, 'text' => "Yangi bot nomini kiriting:", 'reply_markup' => $cancel_menu]);
        exit();
    }

    if ($text == "✉️ Xabar yuborish") {
        $user['step'] = 'admin_broadcast';
        saveUser($chat_id, $user);
        bot('sendMessage', ['chat_id' => $chat_id, 'text' => "Xabar matnini yuboring (barcha foydalanuvchilarga boradi):", 'reply_markup' => $cancel_menu]);
        exit();
    }

    // Admin Steps
    if ($user['step'] == 'admin_add_bot_name' && $text != "🚫 Bekor qilish") {
        $user['temp_bot_name'] = $text;
        $user['step'] = 'admin_add_bot_price';
        saveUser($chat_id, $user);
        bot('sendMessage', ['chat_id' => $chat_id, 'text' => "Bot narxini kiriting (so'mda):"]);
        exit();
    }

    if ($user['step'] == 'admin_add_bot_price' && is_numeric($text)) {
        $user['temp_bot_price'] = $text;
        $user['step'] = 'admin_add_bot_code';
        saveUser($chat_id, $user);
        bot('sendMessage', ['chat_id' => $chat_id, 'text' => "Bot asosiy faylini (index.php) kodini yoki fayl ID sini yuboring:"]);
        exit();
    }

    if ($user['step'] == 'admin_add_bot_code') {
        $bots = getGlobalData('bots_list');
        $new_id = uniqid();
        $bots[$new_id] = [
            'id' => $new_id,
            'name' => $user['temp_bot_name'],
            'price' => $user['temp_bot_price'],
            'content' => $text, // Fayl kodi yoki matni
            'status' => 'active'
        ];
        saveGlobalData('bots_list', $bots);
        
        $user['step'] = 'none';
        saveUser($chat_id, $user);
        bot('sendMessage', ['chat_id' => $chat_id, 'text' => "✅ Bot muvaffaqiyatli qo'shildi!", 'reply_markup' => $admin_menu]);
        exit();
    }

    if ($user['step'] == 'admin_broadcast') {
        $users = glob("data/users/*.json");
        $count = 0;
        foreach ($users as $u) {
            $u_id = basename($u, '.json');
            bot('sendMessage', [
                'chat_id' => $u_id,
                'text' => "📢 <b>Admin xabari:</b>\n\n" . $text,
                'parse_mode' => 'HTML'
            ]);
            $count++;
        }
        $user['step'] = 'none';
        saveUser($chat_id, $user);
        bot('sendMessage', ['chat_id' => $chat_id, 'text' => "✅ Xabar $count ta foydalanuvchiga yuborildi.", 'reply_markup' => $admin_menu]);
        exit();
    }
}

// 3. FOYDALANUVCHI MENYULARI

// --- Kabinet ---
if ($text == "👤 Kabinet") {
    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "👤 <b>Sizning kabinetingiz:</b>\n\n🆔 ID: <code>$chat_id</code>\n💰 Balans: <b>{$user['balance']} so'm</b>\n👥 Takliflar: <b>{$user['ref_count']} ta</b>\n📅 A'zo bo'lgan sana: {$user['join_date']}",
        'parse_mode' => 'HTML',
        'reply_markup' => $main_menu
    ]);
}

// --- Yordam ---
if ($text == "📞 Yordam") {
    $user['step'] = 'support_msg';
    saveUser($chat_id, $user);
    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "📝 Adminga murojaatingizni yozib qoldiring:",
        'reply_markup' => $cancel_menu
    ]);
    exit();
}

if ($user['step'] == 'support_msg' && $text != "🚫 Bekor qilish") {
    bot('forwardMessage', [
        'chat_id' => ADMIN_ID,
        'from_chat_id' => $chat_id,
        'message_id' => $message_id
    ]);
    bot('sendMessage', [
        'chat_id' => ADMIN_ID,
        'text' => "Yuqoridagi xabar <code>$chat_id</code> dan keldi. Javob berish uchun /reply ID matn ko'rinishida yozing.",
        'parse_mode' => 'HTML'
    ]);
    
    $user['step'] = 'none';
    saveUser($chat_id, $user);
    bot('sendMessage', ['chat_id' => $chat_id, 'text' => "✅ Xabaringiz adminga yuborildi!", 'reply_markup' => $main_menu]);
    exit();
}

// --- Admin Reply ---
if (strpos($text, "/reply") === 0 && $chat_id == ADMIN_ID) {
    $ex = explode(" ", $text, 3);
    if (isset($ex[1]) && isset($ex[2])) {
        bot('sendMessage', [
            'chat_id' => $ex[1],
            'text' => "👨‍💻 <b>Admin javobi:</b>\n\n" . $ex[2],
            'parse_mode' => 'HTML'
        ]);
        bot('sendMessage', ['chat_id' => $chat_id, 'text' => "✅ Javob yuborildi."]);
    } else {
        bot('sendMessage', ['chat_id' => $chat_id, 'text' => "Xato format! /reply ID matn"]);
    }
    exit();
}

// --- Pul Ishlash ---
if ($text == "💸 Pul ishlash") {
    $keyboard = json_encode([
        'inline_keyboard' => [
            [['text' => "🎁 Kunlik Bonus", 'callback_data' => "daily_bonus"]]
        ]
    ]);
    
    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "💸 <b>Pul ishlash bo'limi</b>\n\n🔗 <b>Sizning referal havolangiz:</b>\nhttps://t.me/" . bot('getMe')->result->username . "?start=$chat_id\n\nHar bir taklif uchun: <b>200 so'm</b> beriladi.",
        'parse_mode' => 'HTML',
        'reply_markup' => $keyboard
    ]);
}

// --- Callback Query (Bonus) ---
if (isset($callback) && $data == "daily_bonus") {
    $last_bonus = $user['last_bonus'];
    $now = time();
    $diff = $now - strtotime($last_bonus);
    
    if ($last_bonus == null || $diff >= 86400) {
        $bonus = rand(50, 500);
        $user['balance'] += $bonus;
        $user['last_bonus'] = date('Y-m-d H:i:s');
        saveUser($chat_id, $user);
        
        bot('answerCallbackQuery', [
            'callback_query_id' => $cb_id,
            'text' => "✅ Siz $bonus so'm bonus oldingiz!",
            'show_alert' => true
        ]);
        bot('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "💰 Balansingizga $bonus so'm qo'shildi. Ertaga yana keling!",
            'parse_mode' => 'HTML'
        ]);
    } else {
        bot('answerCallbackQuery', [
            'callback_query_id' => $cb_id,
            'text' => "❌ Bonusni 24 soatda bir marta olish mumkin.",
            'show_alert' => true
        ]);
    }
}

// --- Hisobni to'ldirish ---
if ($text == "💰 Hisobni to'ldirish") {
    $user['step'] = 'deposit_amount';
    saveUser($chat_id, $user);
    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "💰 <b>Hisobni to'ldirish</b>\n\nQancha summaga to'ldirmoqchisiz? (raqamlarda yozing, min: 1000 so'm)",
        'parse_mode' => 'HTML',
        'reply_markup' => $cancel_menu
    ]);
    exit();
}

if ($user['step'] == 'deposit_amount' && is_numeric($text) && $text >= 1000) {
    $user['temp_amount'] = $text;
    $user['step'] = 'deposit_receipt';
    saveUser($chat_id, $user);
    
    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "💳 <b>To'lov ma'lumotlari:</b>\n\nKarta: <code>$card_number</code>\nEgasi: <b>$card_holder</b>\nSumma: <b>$text so'm</b>\n\nIltimos, to'lovni amalga oshirib, chek rasmini shu yerga yuboring.",
        'parse_mode' => 'HTML'
    ]);
    exit();
}

if ($user['step'] == 'deposit_receipt' && $photo) {
    $file_id = $photo[count($photo)-1]->file_id;
    $amount = $user['temp_amount'];
    
    // Adminga yuborish
    $kb = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "✅ Tasdiqlash", 'callback_data' => "pay_ok_{$chat_id}_{$amount}"],
                ['text' => "❌ Bekor qilish", 'callback_data' => "pay_no_{$chat_id}"]
            ]
        ]
    ]);
    
    bot('sendPhoto', [
        'chat_id' => ADMIN_ID,
        'photo' => $file_id,
        'caption' => "📩 <b>Yangi to'lov!</b>\n\n👤 User: <a href='tg://user?id=$chat_id'>$name</a> ($chat_id)\n💰 Summa: <b>$amount so'm</b>",
        'parse_mode' => 'HTML',
        'reply_markup' => $kb
    ]);
    
    $user['step'] = 'none';
    saveUser($chat_id, $user);
    
    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "✅ Chek qabul qilindi. Admin tasdiqlagandan so'ng balansingiz to'ldiriladi.",
        'reply_markup' => $main_menu
    ]);
    exit();
}

// --- Admin To'lov Tasdiqlash ---
if (isset($callback) && strpos($data, "pay_") === 0 && $chat_id == ADMIN_ID) {
    $ex = explode("_", $data);
    $action = $ex[1]; // ok yoki no
    $u_id = $ex[2];
    
    if ($action == "ok") {
        $sum = $ex[3];
        $u_data = getUser($u_id);
        $u_data['balance'] += $sum;
        saveUser($u_id, $u_data);
        
        bot('sendMessage', [
            'chat_id' => $u_id,
            'text' => "✅ <b>To'lovingiz tasdiqlandi!</b>\nBalansingizga $sum so'm qo'shildi."
        ]);
        bot('editMessageCaption', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'caption' => "✅ Tasdiqlandi: $sum so'm ($u_id ga qo'shildi)"
        ]);
    } else {
        bot('sendMessage', [
            'chat_id' => $u_id,
            'text' => "❌ To'lovingiz bekor qilindi. Iltimos, admin bilan bog'laning."
        ]);
        bot('editMessageCaption', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'caption' => "❌ Bekor qilindi."
        ]);
    }
}

// --- BOT OCHISH (Konstruktor qismi) ---
if ($text == "🤖 Bot ochish") {
    $bots = getGlobalData('bots_list');
    
    if (empty($bots)) {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "🤷‍♂️ Hozircha botlar mavjud emas.",
            'reply_markup' => $main_menu
        ]);
        exit();
    }
    
    $keys = [];
    foreach ($bots as $id => $b) {
        $keys[] = [['text' => $b['name'] . " - " . $b['price'] . " so'm", 'callback_data' => "buybot_" . $id]];
    }
    
    $kb = json_encode(['inline_keyboard' => $keys]);
    
    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "🤖 <b>Mavjud botlar ro'yxati:</b>\n\nKerakli botni tanlang:",
        'parse_mode' => 'HTML',
        'reply_markup' => $kb
    ]);
}

if (isset($callback) && strpos($data, "buybot_") === 0) {
    $bid = explode("_", $data)[1];
    $bots = getGlobalData('bots_list');
    
    if (isset($bots[$bid])) {
        $selected_bot = $bots[$bid];
        $price = $selected_bot['price'];
        
        if ($user['balance'] >= $price) {
            // Sotib olish jarayoni
            $user['balance'] -= $price;
            $user['step'] = 'wait_token_' . $bid; // Token kiritishni kutish
            saveUser($chat_id, $user);
            
            bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
            bot('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "✅ <b>Xarid muvaffaqiyatli!</b>\nBalansdan $price so'm yechildi.\n\nEndi botingizni faollashtirish uchun @BotFather dan olgan <b>TOKEN</b>ingizni yuboring:",
                'parse_mode' => 'HTML',
                'reply_markup' => $cancel_menu
            ]);
        } else {
            bot('answerCallbackQuery', [
                'callback_query_id' => $cb_id,
                'text' => "❌ Mablag' yetarli emas! Balansingizni to'ldiring.",
                'show_alert' => true
            ]);
        }
    }
}

// --- Tokenni qabul qilish va botni "faollashtirish" ---
if (strpos($user['step'], 'wait_token_') === 0 && $text != "🚫 Bekor qilish") {
    $bid = explode("_", $user['step'])[2];
    $bots = getGlobalData('bots_list');
    $token = $text;
    
    // Tokenni oddiy tekshirish (Regex)
    if (preg_match('/^\d+:[A-Za-z0-9_-]+$/', $token)) {
        
        // Bu yerda haqiqiy hostingda webhook sozlash logikasi bo'lishi kerak.
        // Lekin bitta fayl bo'lgani uchun biz Kodni yuboramiz.
        
        $source_code = $bots[$bid]['content'];
        $final_code = str_replace("TOKEN_O'RNI", $token, $source_code); // Kod ichida token joyini almashtirish (agar shablon bo'lsa)
        
        $filename = "data/" . $bots[$bid]['name'] . "_code.php";
        file_put_contents($filename, $source_code); // Kodni faylga yozamiz
        
        bot('sendDocument', [
            'chat_id' => $chat_id,
            'document' => new CURLFile($filename),
            'caption' => "🎉 <b>Tabriklaymiz!</b> Botingiz tayyor.\n\nFaylni yuklab olib hostingingizga joylang va webhookni sozlang.\n\nToken: <code>$token</code>",
            'parse_mode' => 'HTML',
            'reply_markup' => $main_menu
        ]);
        
        unlink($filename); // Vaqtinchalik faylni o'chiramiz
        
        $user['step'] = 'none';
        saveUser($chat_id, $user);
        
        // Adminni xabardor qilish
        bot('sendMessage', [
            'chat_id' => ADMIN_ID,
            'text' => "ℹ️ Foydalanuvchi ($chat_id) <b>{$bots[$bid]['name']}</b> ni sotib oldi va token kiritdi."
        ]);
        
    } else {
        bot('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ Token noto'g'ri formatda! Qayta yuboring:"]);
    }
    exit();
}
?>
