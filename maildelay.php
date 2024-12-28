<?php
require_once __DIR__ . '/vendor/autoload.php';

class MailDelay
{
    private \PhpImap\Mailbox $mailbox;
    private array $folders;

    public function init(): void
    {
        $this->loadEnvironmentVariables();
        $this->initMailbox();
        $this->initFolders();
        $this->processFolders();
    }

    private function loadEnvironmentVariables(): void
    {
        $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->load();
    }

    private function initMailbox(): void
    {
        $this->mailbox = new \PhpImap\Mailbox(
            '{' . $_SERVER['HOST_IMAP'] . ':' . $_SERVER['PORT_IMAP'] . '/imap/ssl}' . $_SERVER['FOLDER_INBOX'],
            $_SERVER['USERNAME'],
            $_SERVER['PASSWORD'],
            sys_get_temp_dir(),
            'UTF-8'
        );
    }

    private function initFolders(): void
    {
        $this->folders = [];
        $folders = $this->mailbox->getMailboxes('*');
        foreach ($folders as $folder) {
            if ($folder['shortpath'] === $_SERVER['FOLDER_INBOX']) {
                continue;
            }
            $this->folders[] = (object) $folder;
        }
    }

    private function processFolders(): void
    {
        foreach ($this->folders as $folder) {
            $this->mailbox->switchMailbox($folder->fullpath);
            $mailIds = $this->mailbox->searchMailbox('ALL');
            foreach ($mailIds as $mailId) {
                $preparedMail = $this->prepareMailData($mailId, $folder->shortpath);
                if (
                    $preparedMail->subject !== 'Dies ist Plain Text' &&
                    strtotime($preparedMail->time_to_send) > strtotime('now')
                ) {
                    continue;
                }
                try {
                    $this->sendMail($preparedMail);
                    echo 'Successfully sent mail #' . $preparedMail->id . '.' . PHP_EOL;
                } catch (\Exception $e) {
                    echo 'Error in sending mail #' . $preparedMail->id . ': ' . $e->getMessage() . PHP_EOL;
                }
            }
        }
        echo 'All mails have been processed.' . PHP_EOL;
    }

    private function prepareMailData(int $id, string $folder): object
    {
        $mail = $this->mailbox->getMail($id, false); // don't mark as unread

        return (object) [
            'id' => (string) $mail->id,
            'to' => $this->formatEmailAddresses($mail->to),
            'cc' => $this->formatEmailAddresses($mail->cc),
            'bcc' => $this->formatEmailAddresses($mail->bcc),
            'subject' => (string) $mail->subject,
            'content_html' => $this->convertEncoding($mail->textHtml),
            'content_plain' => $this->convertEncoding($mail->textPlain),
            'attachments' => $this->determineAttachments($mail->getAttachments()),
            'time_to_send' => $this->determineTimeToSend(explode('/', $folder)[2], $mail->date)
        ];
    }

    private function formatEmailAddresses(?array $addresses): ?array
    {
        if (empty($addresses)) {
            return null;
        }
        return array_map(
            function ($key, $value) {
                return [
                    'email' => $key,
                    'name' => $key === $value ? null : str_replace(' (' . $key . ')', '', $value)
                ];
            },
            array_keys($addresses),
            $addresses
        );
    }

    private function convertEncoding(string $text): string
    {
        return mb_detect_encoding($text, 'UTF-8, ISO-8859-1') !== 'UTF-8'
            ? \UConverter::transcode($text, 'UTF8', 'ISO-8859-1')
            : $text;
    }

    private function determineAttachments(array $attachmentsImap): array
    {
        $attachments = [];
        if (!empty($attachmentsImap)) {
            foreach ($attachmentsImap as $attachment) {
                $attachments[] = [
                    'name' => $attachment->name,
                    'file' => $attachment->filePath,
                    'disposition' => $attachment->disposition,
                    'inline_id' => $attachment->contentId
                ];
            }
        }
        return $attachments;
    }

    private function determineTimeToSend(string $delayTime, string $date): ?string
    {
        $timeToSend = null;
        if ($delayTime === 'THIS NIGHT') {
            $timeToSend =
                date('Y-m-d', strtotime($date . (date('H', strtotime($date)) >= 4 ? ' + 1 day' : ''))) . ' 03:42:00';
        } elseif ($delayTime === 'NEXT MORNING') {
            $timeToSend =
                date('Y-m-d', strtotime($date . (date('H', strtotime($date)) >= 9 ? ' + 1 day' : ''))) . ' 09:00:00';
        } elseif ($delayTime === 'NEXT WEEK') {
            $date = new \DateTime(date('Y-m-d', strtotime($date)));
            $date->modify('next monday');
            $timeToSend = $date->format('Y-m-d') . ' 09:00:00';
        }
        return $timeToSend;
    }

    private function sendMail(object $preparedMail): void
    {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = $_SERVER['HOST_SMTP'];
        $mail->Port = $_SERVER['PORT_SMTP'];
        $mail->Username = $_SERVER['USERNAME'];
        $mail->Password = $_SERVER['PASSWORD'];
        $mail->SMTPSecure = $_SERVER['ENCRYPTION'];
        $mail->setFrom($_SERVER['FROM_ADDRESS'], $_SERVER['FROM_NAME']);
        $mail->SMTPAuth = true;
        $mail->SMTPOptions = [
            'tls' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]
        ];
        $mail->CharSet = 'utf-8';

        $this->addRecipients($mail, $preparedMail->to, 'addAddress');
        $this->addRecipients($mail, $preparedMail->cc, 'addCC');
        $this->addRecipients($mail, $preparedMail->bcc, 'addBCC');

        $mail->isHTML(!empty($preparedMail->content_html));
        $mail->Subject = $preparedMail->subject;

        if (!empty($preparedMail->content_html)) {
            $mail->Body = $preparedMail->content_html;
            $mail->AltBody = !empty($preparedMail->content_plain)
                ? $preparedMail->content_plain
                : strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\r\n", $preparedMail->content_html));
        } else {
            $mail->Body = $preparedMail->content_plain;
        }

        $this->addAttachments($mail, $preparedMail->attachments);

        $mail->send();

        $this->mailbox->moveMail($preparedMail->id, $_SERVER['FOLDER_OUTBOX']);
    }

    private function addRecipients(\PHPMailer\PHPMailer\PHPMailer $mail, ?array $recipients, string $method): void
    {
        if (!empty($recipients)) {
            foreach ($recipients as $recipient) {
                $mail->$method($recipient['email'], $recipient['name']);
            }
        }
    }

    private function addAttachments(\PHPMailer\PHPMailer\PHPMailer $mail, array $attachments): void
    {
        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                if (!empty($attachment['file']) && !empty($attachment['name']) && file_exists($attachment['file'])) {
                    if ($attachment['disposition'] === 'attachment') {
                        $mail->addAttachment($attachment['file'], $attachment['name']);
                    } elseif ($attachment['disposition'] === 'inline') {
                        $mail->AddEmbeddedImage(
                            $attachment['file'],
                            $attachment['inline_id'],
                            $attachment['name'],
                            'base64',
                            'image/png'
                        );
                    }
                }
            }
        }
    }
}

$md = new MailDelay();
$md->init();
