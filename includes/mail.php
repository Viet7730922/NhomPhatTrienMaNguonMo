<?php
/**
 * Gửi email bằng PHPMailer qua SMTP.
 *
 * Cấu hình nằm ở config/mail.local.php (KHÔNG commit - đã có trong .gitignore). Mẫu: config/mail.example.php.
 * Chưa có file đó (hoặc 'enabled' => false) thì web vẫn chạy bình thường, chỉ là không gửi mail.
 *
 * NGUYÊN TẮC: gửi mail lỗi KHÔNG được làm hỏng chức năng chính (tạo bài tập, nộp bài).
 * Vì vậy mọi hàm ở đây không ném exception; kết quả trả về dạng mảng để nơi gọi tự quyết định.
 *
 * File này chỉ lo phần "vận chuyển" thư. Nội dung từng loại thư nằm ở includes/notify.php.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../libs/PHPMailer/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

/** Đọc cấu hình mail (chỉ đọc file 1 lần mỗi request). Trả về null nếu chưa cấu hình hoặc đang tắt. */
function mailConfig(): ?array
{
    static $cfg = false;                              // false = chưa đọc; null = đã đọc nhưng không dùng được

    if ($cfg === false) {
        $cfg  = null;
        $file = __DIR__ . '/../config/mail.local.php';
        $c    = is_file($file) ? require $file : null;

        if (is_array($c) && !empty($c['enabled']) && !empty($c['host']) && !empty($c['from_email'])) {
            $cfg = $c + [                             // giá trị mặc định cho khóa bị thiếu
                'port'       => 587,
                'encryption' => 'tls',
                'username'   => '',
                'password'   => '',
                'from_name'  => SITE_NAME,
            ];
        }
    }
    return $cfg;
}

function mailEnabled(): bool
{
    return mailConfig() !== null;
}

/** Tạo đối tượng PHPMailer đã cài sẵn SMTP. Lỗi sẽ ném exception (nơi gọi bắt). */
function mailCreate(array $cfg): PHPMailer
{
    $m = new PHPMailer(true);                         // true = báo lỗi bằng exception
    $m->isSMTP();
    $m->Host          = (string) $cfg['host'];
    $m->Port          = (int) $cfg['port'];
    $m->SMTPAuth      = $cfg['username'] !== '';
    $m->Username      = (string) $cfg['username'];
    $m->Password      = (string) $cfg['password'];
    $m->SMTPKeepAlive = true;                         // gửi nhiều thư trên CÙNG 1 kết nối cho nhanh
    $m->Timeout       = 10;                           // chờ SMTP tối đa 10 giây, tránh treo trang

    if ($cfg['encryption'] === 'ssl') {
        $m->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($cfg['encryption'] === 'tls') {
        $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } else {
        $m->SMTPSecure  = '';
        $m->SMTPAutoTLS = false;
    }

    $m->CharSet  = PHPMailer::CHARSET_UTF8;           // để tiếng Việt không bị lỗi font
    $m->Encoding = PHPMailer::ENCODING_BASE64;
    $m->setFrom((string) $cfg['from_email'], (string) $cfg['from_name']);
    return $m;
}

/**
 * Gửi CÙNG MỘT tiêu đề cho nhiều người, MỖI NGƯỜI MỘT THƯ RIÊNG (không lộ email của nhau).
 *
 * @param array    $recipients danh sách [['email' => ..., 'name' => ...], ...]
 * @param callable $render     function (array $recipient): string  -> HTML của thư gửi cho người đó
 * @param array|null $replyTo  ['email' => ..., 'name' => ...] nếu muốn người nhận bấm "Trả lời" là gửi cho người này
 *
 * @return array ['configured' => bool, 'sent' => int, 'failed' => int, 'error' => string]
 */
function sendMailEach(array $recipients, string $subject, callable $render, ?array $replyTo = null): array
{
    $result = ['configured' => false, 'sent' => 0, 'failed' => 0, 'error' => ''];

    $cfg = mailConfig();
    if ($cfg === null) {
        return $result;
    }
    $result['configured'] = true;

    // Địa chỉ sai định dạng thì bỏ qua ngay (không cần hỏi máy chủ SMTP)
    $valid = [];
    foreach ($recipients as $r) {
        if (filter_var($r['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $valid[] = $r;
        } else {
            $result['failed']++;
        }
    }
    if (!$valid) {
        return $result;
    }

    $subject = trim(preg_replace('/[\r\n]+/', ' ', $subject));   // chống chèn thêm dòng header
    @set_time_limit(120);                                          // gửi nhiều thư có thể lâu hơn 30 giây mặc định

    $mail = null;
    try {
        $mail = mailCreate($cfg);
        if ($replyTo && filter_var($replyTo['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($replyTo['email'], (string) ($replyTo['name'] ?? ''));
        }
    } catch (Throwable $ex) {
        $result['failed'] += count($valid);
        $result['error']   = $ex->getMessage();
        error_log('[mail] ' . $result['error']);
        return $result;
    }

    foreach ($valid as $i => $r) {
        try {
            $mail->clearAddresses();
            $mail->addAddress($r['email'], (string) ($r['name'] ?? ''));
            $mail->Subject = $subject;
            $mail->msgHTML($render($r));              // tự tạo luôn bản chữ thuần cho trình đọc mail không hiện được HTML
            $mail->send();
            $result['sent']++;
        } catch (Throwable $ex) {
            $result['failed']++;
            $result['error'] = $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $ex->getMessage();
            error_log('[mail] gửi cho ' . $r['email'] . ' lỗi: ' . $result['error']);

            // Mất kết nối / sai mật khẩu SMTP: dừng luôn, đừng thử tiếp từng người (mỗi lần lại chờ vài giây).
            // Còn nếu chỉ riêng 1 địa chỉ bị từ chối thì kết nối vẫn sống -> gửi tiếp cho người khác.
            if (!$mail->getSMTPInstance()->connected()) {
                $result['failed'] += count($valid) - $i - 1;
                break;
            }
        }
    }

    try {
        $mail->smtpClose();
    } catch (Throwable $ex) {
        // đóng kết nối lỗi cũng không sao
    }
    return $result;
}

/**
 * Khung HTML đơn giản cho thư. Email chỉ hiển thị đúng khi viết CSS ngay trong thẻ (inline style).
 * $bodyHtml là HTML ĐÃ ĐƯỢC escape bởi nơi gọi.
 * $button: ['text' => ..., 'url' => ...] hoặc null.
 */
function mailLayout(string $heading, string $bodyHtml, ?array $button = null): string
{
    $btn = '';
    if ($button) {
        $btn = '<p style="margin:24px 0 8px"><a href="' . e($button['url']) . '" style="background:#0d6efd;color:#ffffff;'
             . 'padding:10px 18px;border-radius:6px;text-decoration:none;display:inline-block">'
             . e($button['text']) . '</a></p>';
    }

    return '<!DOCTYPE html><html lang="vi"><body style="margin:0;padding:16px;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#212529">'
         . '<div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #dee2e6">'
         . '<div style="background:#0d6efd;color:#ffffff;padding:14px 20px;font-size:18px;font-weight:bold">' . e(SITE_NAME) . '</div>'
         . '<div style="padding:20px">'
         . '<h2 style="margin:0 0 14px;font-size:18px">' . e($heading) . '</h2>'
         . $bodyHtml . $btn
         . '</div>'
         . '<div style="padding:12px 20px;font-size:12px;color:#6c757d;border-top:1px solid #dee2e6">'
         . 'Thư tự động từ hệ thống ' . e(SITE_NAME) . '.'
         . '</div></div></body></html>';
}
