<?php

namespace Cma\Services;

use App\Library\Application;
use App\Library\Arr;
use App\Library\Database;
use App\Library\Email;

/**
 * EmailLogService
 *
 * Logs all sent emails to tblEmailLog and provides resend/delete functionality.
 * Records are automatically cleaned up after 30 days.
 */
class EmailLogService
{
    private static int $cleanupCounter = 0;

    /**
     * Log an email send attempt
     *
     * @param array $data Email data from the afterSend hook
     */
    public static function log(array $data): void
    {
        try {
            $conn = Database::getConnection('data');
            if ($conn === null) return;

            // Format recipients as readable strings
            $to = self::formatAddresses($data['to'] ?? []);
            $cc = self::formatAddresses($data['cc'] ?? []);
            $bcc = self::formatAddresses($data['bcc'] ?? []);

            // Determine status
            $status = 'sent';
            if (!empty($data['simulation'])) {
                $status = 'simulated';
            } elseif (empty($data['success'])) {
                $status = 'error';
            }

            // Get current CMA user if available
            $user = '';
            if (class_exists('\\Cma\\SecurityHelper')) {
                try {
                    $userData = \Cma\SecurityHelper::getCurrentUserData();
                    if ($userData) {
                        $user = $userData['userFullName'] ?? $userData['userLogin'] ?? '';
                    }
                } catch (\Exception $e) {
                    // Not logged in or security not available
                }
            }

            // Get environment
            $environment = Application::get('omgeving', '');

            // Format attachments
            $attachments = '';
            if (!empty($data['attachments'])) {
                $attachments = json_encode($data['attachments'], JSON_UNESCAPED_UNICODE);
            }

            $sql = "INSERT INTO tblEmailLog (datestamp, mail_to, mail_cc, mail_bcc, mail_from, mail_from_name, mail_subject, mail_body, mail_attachments, mail_status, mail_error, mail_environment, mail_user) VALUES (Now(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $to,
                $cc,
                $bcc,
                $data['from'] ?? '',
                $data['fromName'] ?? '',
                mb_substr($data['subject'] ?? '', 0, 255),
                $data['body'] ?? '',
                $attachments,
                $status,
                $data['error'] ?? '',
                $environment,
                $user
            ]);

            // Cleanup old records periodically (every 10th insert)
            self::$cleanupCounter++;
            if (self::$cleanupCounter % 10 === 0) {
                self::cleanup($conn);
            }
        } catch (\Exception $e) {
            error_log('EmailLogService::log failed: ' . $e->getMessage());
        }
    }

    /**
     * Resend an email by ID
     *
     * @param int $id Email log record ID
     * @return array ['success' => bool, 'error' => string]
     */
    public static function resend(int $id): array
    {
        try {
            $record = self::getById($id);
            if (!$record) {
                return ['success' => false, 'error' => 'E-mail niet gevonden'];
            }

            $email = new Email();
            $email->setSubject($record['mail_subject'])
                  ->setBody($record['mail_body'])
                  ->setFrom($record['mail_from'], $record['mail_from_name'])
                  ->setUseTemplate(false);

            // Parse and add recipients
            $recipients = self::parseAddresses($record['mail_to']);
            foreach ($recipients as $addr) {
                $email->addRecipient($addr);
            }

            // Parse and add CC
            if (!empty($record['mail_cc'])) {
                $ccRecipients = self::parseAddresses($record['mail_cc']);
                foreach ($ccRecipients as $addr) {
                    $email->addRecipientsCC($addr);
                }
            }

            // Add attachments if they still exist
            if (!empty($record['mail_attachments'])) {
                $attachments = json_decode($record['mail_attachments'], true);
                if (Arr::isArray($attachments)) {
                    foreach ($attachments as $path) {
                        if (file_exists($path)) {
                            $email->addAttachment($path);
                        }
                    }
                }
            }

            if (!$email->send()) {
                return ['success' => false, 'error' => $email->getError()];
            }

            return ['success' => true, 'error' => ''];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delete an email log record
     *
     * @param int $id Email log record ID
     * @return bool
     */
    public static function delete(int $id): bool
    {
        try {
            $conn = Database::getConnection('data');
            if ($conn === null) return false;

            $stmt = $conn->prepare("DELETE FROM tblEmailLog WHERE ID = ?");
            $stmt->execute([$id]);
            return true;
        } catch (\Exception $e) {
            error_log('EmailLogService::delete failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a single email log record by ID
     *
     * @param int $id
     * @return array|null
     */
    public static function getById(int $id): ?array
    {
        try {
            $conn = Database::getConnection('data');
            if ($conn === null) return null;

            $stmt = $conn->prepare("SELECT * FROM tblEmailLog WHERE ID = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Delete records older than 30 days
     *
     * @param \PDO|null $conn Optional connection (reuse existing)
     */
    public static function cleanup(?\PDO $conn = null): void
    {
        try {
            if ($conn === null) {
                $conn = Database::getConnection('data');
            }
            if ($conn === null) return;

            $conn->exec("DELETE FROM tblEmailLog WHERE datestamp < DateAdd('d', -30, Now())");
        } catch (\Exception $e) {
            error_log('EmailLogService::cleanup failed: ' . $e->getMessage());
        }
    }

    /**
     * Format PHPMailer address array to readable string
     * Input: [[email, name], [email, name], ...]
     * Output: "Name <email>, Name2 <email2>"
     */
    private static function formatAddresses(array $addresses): string
    {
        $parts = [];
        foreach ($addresses as $addr) {
            if (Arr::isArray($addr)) {
                $email = $addr[0] ?? '';
                $name = $addr[1] ?? '';
                $parts[] = !empty($name) ? "$name <$email>" : $email;
            } else {
                $parts[] = $addr;
            }
        }
        return implode(', ', $parts);
    }

    /**
     * Parse formatted address string back to array of email addresses
     * Input: "Name <email>, Name2 <email2>" or "email1, email2"
     * Output: ["email1", "email2"]
     */
    private static function parseAddresses(string $addressString): array
    {
        $addresses = [];
        $parts = explode(',', $addressString);
        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) continue;
            // Extract email from "Name <email>" format
            if (preg_match('/<([^>]+)>/', $part, $m)) {
                $addresses[] = $m[1];
            } else {
                $addresses[] = $part;
            }
        }
        return $addresses;
    }
}
