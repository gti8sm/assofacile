<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Db;
use App\Support\Access;
use App\Support\GoogleDrive;
use App\Support\MedicalAccess;
use App\Support\Modules;
use App\Support\Session;
use App\Support\Storage;

final class MembersController
{
    private const MAX_DOC_FILE_SIZE = 10485760; // 10 MB

    private static function slugifyFolderName(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        $value = mb_strtolower($value);
        $value = iconv('UTF-8', 'ASCII//TRANSLIT', $value) ?: $value;
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? $value;
        $value = trim($value, '-');
        $value = preg_replace('/-+/', '-', $value) ?? $value;
        if ($value === '') {
            $value = 'member';
        }
        if (mb_strlen($value) > 80) {
            $value = mb_substr($value, 0, 80);
            $value = rtrim($value, '-');
        }
        return $value;
    }

    private static function normalizeTierName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;
        $name = mb_strtolower($name);
        $name = iconv('UTF-8', 'ASCII//TRANSLIT', $name) ?: $name;
        $name = preg_replace('/[^a-z0-9]+/i', ' ', $name) ?? $name;
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);
        if ($name === '') {
            $name = 'member';
        }
        if (mb_strlen($name) > 190) {
            $name = mb_substr($name, 0, 190);
        }
        return $name;
    }

    private static function upsertMemberTier(\PDO $pdo, int $tenantId, int $memberId, string $displayName): void
    {
        $displayName = trim(preg_replace('/\s+/', ' ', $displayName) ?? $displayName);
        if ($displayName === '') {
            $displayName = 'Adhérent #' . $memberId;
        }
        if (mb_strlen($displayName) > 190) {
            $displayName = mb_substr($displayName, 0, 190);
        }

        $norm = self::normalizeTierName($displayName);

        $stmt = $pdo->prepare(
            'INSERT INTO tiers (tenant_id, member_id, name, normalized_name)
             VALUES (:tenant_id, :member_id, :name, :normalized_name)
             ON DUPLICATE KEY UPDATE name = VALUES(name), normalized_name = VALUES(normalized_name)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'member_id' => $memberId,
            'name' => $displayName,
            'normalized_name' => $norm,
        ]);

        $stmt = $pdo->prepare('SELECT id FROM tiers WHERE tenant_id = :tenant_id AND member_id = :member_id LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId, 'member_id' => $memberId]);
        $tier = $stmt->fetch();
        $tierId = (int)($tier['id'] ?? 0);
        if ($tierId > 0) {
            $stmt = $pdo->prepare('INSERT IGNORE INTO tier_tags (tier_id, tag) VALUES (:tier_id, :tag)');
            $stmt->execute(['tier_id' => $tierId, 'tag' => 'adherent']);
        }
    }

    public static function index(): void
    {
        Access::require('members', 'read');

        $tenantId = (int)$_SESSION['tenant_id'];
        $q = trim((string)($_GET['q'] ?? ''));
        $status = (string)($_GET['status'] ?? '');
        if (!in_array($status, ['', 'active', 'inactive'], true)) {
            $status = '';
        }

        $sql = 'SELECT id, first_name, last_name, email, phone, status, member_since, membership_paid_until, created_at
                FROM members
                WHERE tenant_id = :tenant_id';
        $params = ['tenant_id' => $tenantId];

        if ($status !== '') {
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }

        if ($q !== '') {
            $sql .= ' AND (
                CONCAT(COALESCE(first_name, \'\'), \' \', COALESCE(last_name, \'\')) LIKE :q
                OR COALESCE(email, \'\') LIKE :q
                OR COALESCE(phone, \'\') LIKE :q
            )';
            $params['q'] = '%' . $q . '%';
        }

        $sql .= ' ORDER BY status ASC, last_name ASC, first_name ASC, id DESC';

        $pdo = Db::pdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $members = $stmt->fetchAll();

        $flash = Session::flash('success');
        $error = Session::flash('error');

        require base_path('views/members/index.php');
    }

    public static function create(): void
    {
        Access::require('members', 'write');

        $createType = (string)($_GET['type'] ?? '');
        if (!in_array($createType, ['', 'child'], true)) {
            $createType = '';
        }
        $prefillHouseholdId = (int)($_GET['household_id'] ?? 0);
        $returnTo = (string)($_GET['return_to'] ?? '');

        $suggestedParent = null;
        $availableParents = [];
        if ($createType === 'child' && $prefillHouseholdId > 0) {
            $pdo = Db::pdo();
            $stmt = $pdo->prepare(
                'SELECT id, first_name, last_name, email, phone
                 FROM members
                 WHERE tenant_id = :tenant_id
                   AND household_id = :household_id
                   AND relationship IN (\'adult\', \'spouse\')
                 ORDER BY relationship ASC, id ASC'
            );
            $stmt->execute([
                'tenant_id' => (int)$_SESSION['tenant_id'],
                'household_id' => $prefillHouseholdId,
            ]);
            $availableParents = $stmt->fetchAll();
            $suggestedParent = $availableParents[0] ?? null;
        }

        $error = Session::flash('error');
        require base_path('views/members/new.php');
    }

    public static function store(): void
    {
        Access::require('members', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];

        $first = trim((string)($_POST['first_name'] ?? ''));
        $last = trim((string)($_POST['last_name'] ?? ''));
        $birthDate = trim((string)($_POST['birth_date'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $memberSince = trim((string)($_POST['member_since'] ?? ''));
        $paidUntil = '';
        $address = trim((string)($_POST['address'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        $relationship = (string)($_POST['relationship'] ?? 'adult');
        if (!in_array($relationship, ['adult', 'spouse', 'child'], true)) {
            $relationship = 'adult';
        }
        $householdId = (int)($_POST['household_id'] ?? 0);
        $useHouseholdAddress = (int)($_POST['use_household_address'] ?? 0) === 1 ? 1 : 0;
        $returnTo = (string)($_POST['return_to'] ?? '');
        if ($returnTo !== '' && (!str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//'))) {
            $returnTo = '';
        }

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            Session::flash('error', 'Email invalide.');
            redirect('/members/new');
        }

        if ($memberSince !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $memberSince)) {
            Session::flash('error', 'Date d\'adhésion invalide.');
            redirect('/members/new');
        }

        if ($birthDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate)) {
            Session::flash('error', 'Date de naissance invalide.');
            redirect('/members/new');
        }

        if ($householdId < 0) {
            $householdId = 0;
        }

        $pdo = Db::pdo();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'INSERT INTO members (tenant_id, first_name, last_name, birth_date, email, phone, address, status, member_since, membership_paid_until, notes, household_id, relationship, use_household_address)
                 VALUES (:tenant_id, :first_name, :last_name, :birth_date, :email, :phone, :address, :status, :member_since, :membership_paid_until, :notes, :household_id, :relationship, :use_household_address)'
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'first_name' => ($first !== '' ? $first : null),
                'last_name' => ($last !== '' ? $last : null),
                'birth_date' => ($birthDate !== '' ? $birthDate : null),
                'email' => ($email !== '' ? $email : null),
                'phone' => ($phone !== '' ? $phone : null),
                'address' => ($address !== '' ? $address : null),
                'status' => 'active',
                'member_since' => ($memberSince !== '' ? $memberSince : null),
                'membership_paid_until' => ($paidUntil !== '' ? $paidUntil : null),
                'notes' => ($notes !== '' ? $notes : null),
                'household_id' => ($householdId > 0 ? $householdId : null),
                'relationship' => $relationship,
                'use_household_address' => $useHouseholdAddress,
            ]);
            $memberId = (int)$pdo->lastInsertId();

            $displayName = trim(($first !== '' ? $first : '') . ' ' . ($last !== '' ? $last : ''));
            self::upsertMemberTier($pdo, $tenantId, $memberId, $displayName);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Session::flash('error', 'Erreur lors de la création (email déjà utilisé ?).');
            redirect('/members/new');
        }

        Session::flash('success', 'Adhérent ajouté.');
        if ($returnTo !== '') {
            redirect($returnTo);
        }
        redirect('/members');
    }

    public static function edit(): void
    {
        Access::require('members', 'write');

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo '400';
            return;
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT * FROM members WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
        $stmt->execute([
            'id' => $id,
            'tenant_id' => (int)$_SESSION['tenant_id'],
        ]);
        $member = $stmt->fetch();
        if (!$member) {
            http_response_code(404);
            echo '404';
            return;
        }

        $tenantId = (int)$_SESSION['tenant_id'];
        $userId = (int)$_SESSION['user_id'];
        $canMedical = MedicalAccess::can($tenantId, $userId, (int)$member['id']);

        $stmt = $pdo->prepare('SELECT id, name FROM households WHERE tenant_id = :tenant_id ORDER BY id DESC');
        $stmt->execute(['tenant_id' => $tenantId]);
        $households = $stmt->fetchAll();

        $household = null;
        if (!empty($member['household_id'])) {
            $stmt = $pdo->prepare('SELECT id, name, address FROM households WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
            $stmt->execute([
                'id' => (int)$member['household_id'],
                'tenant_id' => $tenantId,
            ]);
            $household = $stmt->fetch();
        }

        $effectiveAddress = (string)($member['address'] ?? '');
        if (((int)($member['use_household_address'] ?? 0) === 1) && !empty($household)) {
            $effectiveAddress = (string)($household['address'] ?? '');
        }

        $medical = null;
        if ($canMedical) {
            $stmt = $pdo->prepare('SELECT allergies, medical_notes FROM member_medical_profiles WHERE member_id = :member_id LIMIT 1');
            $stmt->execute(['member_id' => (int)$member['id']]);
            $medical = $stmt->fetch();
        }

        $pickups = [];
        if ($canMedical && (string)($member['relationship'] ?? 'adult') === 'child') {
            $stmt = $pdo->prepare(
                'SELECT id, name, phone, relation, notes
                 FROM member_authorized_pickups
                 WHERE member_id = :member_id
                 ORDER BY id DESC'
            );
            $stmt->execute(['member_id' => (int)$member['id']]);
            $pickups = $stmt->fetchAll();
        }

        $membershipProducts = [];
        try {
            $stmt = $pdo->prepare(
                'SELECT id, label, amount_default_cents, period_months
                 FROM membership_products
                 WHERE tenant_id = :tenant_id AND is_active = 1 AND applies_to = \'person\'
                 ORDER BY id DESC'
            );
            $stmt->execute(['tenant_id' => $tenantId]);
            $membershipProducts = $stmt->fetchAll();
        } catch (\Throwable $e) {
            $membershipProducts = [];
        }

        $membershipSubscriptions = [];
        try {
            $stmt = $pdo->prepare(
                'SELECT ms.id, ms.amount_cents, ms.start_date, ms.end_date, ms.status, ms.payment_provider,
                        ms.treasury_transaction_id, ms.payment_external_id,
                        mp.label AS product_label
                 FROM membership_subscriptions ms
                 LEFT JOIN membership_products mp ON mp.id = ms.product_id
                 WHERE ms.tenant_id = :tenant_id AND ms.member_id = :member_id
                 ORDER BY ms.start_date DESC, ms.id DESC'
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'member_id' => (int)$member['id'],
            ]);
            $membershipSubscriptions = $stmt->fetchAll();
        } catch (\Throwable $e) {
            $membershipSubscriptions = [];
        }

        $documents = [];
        try {
            $stmt = $pdo->prepare(
                'SELECT id, title, url, notes, storage_driver, local_path, gdrive_file_id, original_name, mime_type, size_bytes, created_at
                 FROM entity_documents
                 WHERE tenant_id = :tenant_id
                   AND entity_type = "member"
                   AND entity_id = :entity_id
                   AND deleted_at IS NULL
                 ORDER BY id DESC'
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'entity_id' => (int)$member['id'],
            ]);
            $documents = $stmt->fetchAll();
        } catch (\Throwable $e) {
            $documents = [];
        }

        $tabCounts = [
            'memberships' => is_array($membershipSubscriptions) ? count($membershipSubscriptions) : 0,
            'docs' => is_array($documents) ? count($documents) : 0,
        ];

        $error = Session::flash('error');
        $flash = Session::flash('success');
        require base_path('views/members/edit.php');
    }

    public static function update(): void
    {
        Access::require('members', 'write');

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo '400';
            return;
        }

        $tenantId = (int)$_SESSION['tenant_id'];

        $pdo = Db::pdo();

        $first = trim((string)($_POST['first_name'] ?? ''));
        $last = trim((string)($_POST['last_name'] ?? ''));
        $birthDate = trim((string)($_POST['birth_date'] ?? ''));
        $householdId = (int)($_POST['household_id'] ?? 0);
        $relationship = (string)($_POST['relationship'] ?? 'adult');
        $useHouseholdAddress = isset($_POST['use_household_address']) ? 1 : 0;
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $status = (string)($_POST['status'] ?? 'active');
        $memberSince = trim((string)($_POST['member_since'] ?? ''));
        $stmt = $pdo->prepare('SELECT membership_paid_until FROM members WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $stmt->fetch();
        $paidUntil = ($row && $row['membership_paid_until'] !== null) ? (string)$row['membership_paid_until'] : '';
        $address = trim((string)($_POST['address'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        $allergies = trim((string)($_POST['medical_allergies'] ?? ''));
        $medicalNotes = trim((string)($_POST['medical_notes'] ?? ''));

        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        if (!in_array($relationship, ['adult', 'spouse', 'child'], true)) {
            $relationship = 'adult';
        }

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            Session::flash('error', 'Email invalide.');
            redirect('/members/edit?id=' . $id);
        }

        if ($memberSince !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $memberSince)) {
            Session::flash('error', 'Date d\'adhésion invalide.');
            redirect('/members/edit?id=' . $id);
        }

        if ($birthDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate)) {
            Session::flash('error', 'Date de naissance invalide.');
            redirect('/members/edit?id=' . $id);
        }

        try {
            $pdo->beginTransaction();

            if ($householdId > 0) {
                $stmt = $pdo->prepare('SELECT id FROM households WHERE id = :id AND tenant_id = :tenant_id LIMIT 1');
                $stmt->execute([
                    'id' => $householdId,
                    'tenant_id' => $tenantId,
                ]);
                if (!$stmt->fetch()) {
                    $householdId = 0;
                }
            }

            $stmt = $pdo->prepare(
                'UPDATE members
                 SET first_name = :first_name,
                     last_name = :last_name,
                     birth_date = :birth_date,
                     household_id = :household_id,
                     relationship = :relationship,
                     use_household_address = :use_household_address,
                     email = :email,
                     phone = :phone,
                     address = :address,
                     status = :status,
                     member_since = :member_since,
                     membership_paid_until = :membership_paid_until,
                     notes = :notes
                 WHERE id = :id AND tenant_id = :tenant_id'
            );
            $stmt->execute([
                'first_name' => ($first !== '' ? $first : null),
                'last_name' => ($last !== '' ? $last : null),
                'birth_date' => ($birthDate !== '' ? $birthDate : null),
                'household_id' => ($householdId > 0 ? $householdId : null),
                'relationship' => $relationship,
                'use_household_address' => $useHouseholdAddress,
                'email' => ($email !== '' ? $email : null),
                'phone' => ($phone !== '' ? $phone : null),
                'address' => ($address !== '' ? $address : null),
                'status' => $status,
                'member_since' => ($memberSince !== '' ? $memberSince : null),
                'membership_paid_until' => ($paidUntil !== '' ? $paidUntil : null),
                'notes' => ($notes !== '' ? $notes : null),
                'id' => $id,
                'tenant_id' => $tenantId,
            ]);

            if (MedicalAccess::can($tenantId, (int)$_SESSION['user_id'], $id)) {
                $stmt = $pdo->prepare(
                    'INSERT INTO member_medical_profiles (member_id, allergies, medical_notes)
                     VALUES (:member_id, :allergies, :medical_notes)
                     ON DUPLICATE KEY UPDATE
                       allergies = VALUES(allergies),
                       medical_notes = VALUES(medical_notes)'
                );
                $stmt->execute([
                    'member_id' => $id,
                    'allergies' => ($allergies !== '' ? $allergies : null),
                    'medical_notes' => ($medicalNotes !== '' ? $medicalNotes : null),
                ]);
            }

            $displayName = trim(($first !== '' ? $first : '') . ' ' . ($last !== '' ? $last : ''));
            self::upsertMemberTier($pdo, $tenantId, $id, $displayName);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Session::flash('error', 'Erreur lors de la mise à jour (email déjà utilisé ?).');
            redirect('/members/edit?id=' . $id);
        }

        Session::flash('success', 'Adhérent mis à jour.');
        redirect('/members/edit?id=' . $id);
    }

    public static function addDocument(): void
    {
        Access::require('members', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];
        $memberId = (int)($_POST['member_id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $url = trim((string)($_POST['url'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($memberId <= 0 || $title === '' || mb_strlen($title) > 190 || mb_strlen($url) > 500) {
            Session::flash('error', 'Document invalide.');
            redirect('/members');
        }

        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT id, first_name, last_name FROM members WHERE tenant_id = :tenant_id AND id = :id LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId, 'id' => $memberId]);
        $member = $stmt->fetch();
        if (!$member) {
            http_response_code(404);
            echo '404';
            return;
        }

        $memberDisplayName = trim((string)($member['first_name'] ?? '') . ' ' . (string)($member['last_name'] ?? ''));
        $memberSlug = self::slugifyFolderName($memberDisplayName);

        $driver = 'local';
        $localPath = null;
        $gdriveFileId = null;
        $originalName = null;
        $mimeType = null;
        $sizeBytes = null;

        $file = $_FILES['file'] ?? null;
        if (is_array($file) && (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ((int)($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                Session::flash('error', 'Upload invalide.');
                redirect('/members/edit?id=' . $memberId);
            }

            $tmp = (string)($file['tmp_name'] ?? '');
            $orig = (string)($file['name'] ?? 'file');
            $size = (int)($file['size'] ?? 0);
            if ($tmp === '' || !is_file($tmp) || $size <= 0 || $size > self::MAX_DOC_FILE_SIZE) {
                Session::flash('error', 'Fichier invalide (max 10 Mo).');
                redirect('/members/edit?id=' . $memberId);
            }

            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$finfo->file($tmp);
            if ($mime === '') {
                $mime = 'application/octet-stream';
            }

            $ext = pathinfo($orig, PATHINFO_EXTENSION);
            $ext = is_string($ext) ? strtolower(trim($ext)) : '';
            if ($ext === '') {
                $ext = match ($mime) {
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'application/pdf' => 'pdf',
                    default => 'bin',
                };
            }

            $useDrive = Modules::isEnabled($tenantId, 'drive')
                && GoogleDrive::isConfigured()
                && GoogleDrive::isAvailable()
                && GoogleDrive::isConnected($tenantId);

            $drive = $useDrive ? GoogleDrive::getService($tenantId) : null;
            $driveFolderId = $useDrive ? GoogleDrive::getDriveFolderId($tenantId) : null;
            $driveTargetFolderId = null;
            if ($drive !== null) {
                $driveTargetFolderId = GoogleDrive::ensureMemberDocumentsFolder($tenantId, $driveFolderId, $memberId, $memberSlug);
            }

            if ($drive !== null) {
                try {
                    $meta = ['name' => basename($orig)];
                    if ($driveTargetFolderId) {
                        $meta['parents'] = [$driveTargetFolderId];
                    } elseif ($driveFolderId) {
                        $meta['parents'] = [$driveFolderId];
                    }
                    $fileMetadata = new \Google_Service_Drive_DriveFile($meta);

                    $content = file_get_contents($tmp);
                    if ($content !== false) {
                        $created = $drive->files->create($fileMetadata, [
                            'data' => $content,
                            'mimeType' => $mime,
                            'uploadType' => 'multipart',
                            'fields' => 'id',
                        ]);
                        $fid = (string)($created->id ?? '');
                        if ($fid !== '') {
                            $driver = 'gdrive';
                            $gdriveFileId = $fid;
                            $originalName = $orig;
                            $mimeType = $mime;
                            $sizeBytes = $size;
                        }
                    }
                } catch (\Throwable $e) {
                    // fallback local
                }
            }

            if ($gdriveFileId === null) {
                $year = date('Y');
                $month = date('m');
                $baseRelDir = 'tenant_' . $tenantId . '/ged/members/' . $year . '/' . $month;
                $legacyRelDir = $baseRelDir . '/member_' . $memberId;
                $friendlyRelDir = $baseRelDir . '/member_' . $memberId . '_' . $memberSlug;

                $legacyDir = Storage::privatePath($legacyRelDir);
                $friendlyDir = Storage::privatePath($friendlyRelDir);
                $dir = is_dir($legacyDir) ? $legacyDir : $friendlyDir;
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }

                $filename = bin2hex(random_bytes(16)) . '.' . $ext;
                $dest = $dir . DIRECTORY_SEPARATOR . $filename;
                if (!move_uploaded_file($tmp, $dest)) {
                    Session::flash('error', 'Impossible d\'enregistrer le fichier.');
                    redirect('/members/edit?id=' . $memberId);
                }

                $driver = 'local';
                $usedRelDir = ($dir === $legacyDir) ? $legacyRelDir : $friendlyRelDir;
                $localPath = $usedRelDir . '/' . $filename;
                $originalName = $orig;
                $mimeType = $mime;
                $sizeBytes = $size;
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO entity_documents (tenant_id, entity_type, entity_id, title, url, notes, storage_driver, local_path, gdrive_file_id, original_name, mime_type, size_bytes)
             VALUES (:tenant_id, :entity_type, :entity_id, :title, :url, :notes, :driver, :local_path, :gdrive_file_id, :original_name, :mime_type, :size_bytes)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'entity_type' => 'member',
            'entity_id' => $memberId,
            'title' => $title,
            'url' => ($url !== '' ? $url : null),
            'notes' => ($notes !== '' ? $notes : null),
            'driver' => $driver,
            'local_path' => $localPath,
            'gdrive_file_id' => $gdriveFileId,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
        ]);

        Session::flash('success', 'Document ajouté.');
        redirect('/members/edit?id=' . $memberId);
    }

    public static function downloadDocument(): void
    {
        Access::require('members', 'read');

        $tenantId = (int)$_SESSION['tenant_id'];
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo '400';
            return;
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare('SELECT id, entity_id, storage_driver, local_path, gdrive_file_id, original_name, mime_type, size_bytes FROM entity_documents WHERE tenant_id = :tenant_id AND id = :id AND entity_type = "member" AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId, 'id' => $id]);
        $doc = $stmt->fetch();
        if (!$doc) {
            http_response_code(404);
            echo '404';
            return;
        }

        $driver = (string)($doc['storage_driver'] ?? 'local');
        if ($driver === 'gdrive') {
            if (!Modules::isEnabled($tenantId, 'drive')) {
                http_response_code(403);
                echo '403';
                return;
            }

            $service = GoogleDrive::getService($tenantId);
            if (!$service) {
                http_response_code(503);
                echo '503';
                return;
            }

            $fileId = (string)($doc['gdrive_file_id'] ?? '');
            if ($fileId === '') {
                http_response_code(404);
                echo '404';
                return;
            }

            $response = $service->files->get($fileId, ['alt' => 'media']);
            $body = $response->getBody();

            $mime = (string)($doc['mime_type'] ?? 'application/octet-stream');
            $name = (string)($doc['original_name'] ?? 'document');
            header('Content-Type: ' . $mime);
            header('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"');

            while (!$body->eof()) {
                echo $body->read(8192);
            }
            return;
        }

        $rel = (string)($doc['local_path'] ?? '');
        if ($rel === '') {
            http_response_code(404);
            echo '404';
            return;
        }

        $path = Storage::privatePath($rel);
        if (!is_file($path)) {
            http_response_code(404);
            echo '404';
            return;
        }

        $mime = (string)($doc['mime_type'] ?? 'application/octet-stream');
        $name = (string)($doc['original_name'] ?? 'document');
        header('Content-Type: ' . $mime);
        if (!empty($doc['size_bytes'])) {
            header('Content-Length: ' . (string)$doc['size_bytes']);
        }
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"');
        readfile($path);
    }

    public static function deleteDocument(): void
    {
        Access::require('members', 'write');

        $tenantId = (int)$_SESSION['tenant_id'];
        $memberId = (int)($_POST['member_id'] ?? 0);
        $id = (int)($_POST['id'] ?? 0);
        $reason = trim((string)($_POST['delete_reason'] ?? ''));

        if ($memberId <= 0 || $id <= 0) {
            Session::flash('error', 'Suppression invalide.');
            redirect('/members');
        }

        if ($reason !== '' && mb_strlen($reason) > 500) {
            $reason = mb_substr($reason, 0, 500);
        }

        $pdo = Db::pdo();

        $stmt = $pdo->prepare('SELECT first_name, last_name FROM members WHERE tenant_id = :tenant_id AND id = :id LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId, 'id' => $memberId]);
        $mrow = $stmt->fetch() ?: [];
        $memberDisplayName = trim((string)($mrow['first_name'] ?? '') . ' ' . (string)($mrow['last_name'] ?? ''));
        $memberSlug = self::slugifyFolderName($memberDisplayName);

        $stmt = $pdo->prepare('SELECT id, storage_driver, local_path, gdrive_file_id FROM entity_documents WHERE tenant_id = :tenant_id AND id = :id AND entity_type = "member" AND entity_id = :member_id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId, 'id' => $id, 'member_id' => $memberId]);
        $doc = $stmt->fetch();
        if (!$doc) {
            Session::flash('error', 'Document introuvable.');
            redirect('/members/edit?id=' . $memberId);
        }

        $driver = (string)($doc['storage_driver'] ?? 'local');
        $newLocalPath = null;
        $moved = false;

        if ($driver === 'gdrive') {
            try {
                if (Modules::isEnabled($tenantId, 'drive') && GoogleDrive::isConfigured() && GoogleDrive::isAvailable() && GoogleDrive::isConnected($tenantId)) {
                    $service = GoogleDrive::getService($tenantId);
                    $driveFolderId = GoogleDrive::getDriveFolderId($tenantId);
                    $trashFolderId = GoogleDrive::ensureMemberDocumentsTrashFolder($tenantId, $driveFolderId, $memberId, $memberSlug);
                    $fileId = (string)($doc['gdrive_file_id'] ?? '');
                    if ($service && $trashFolderId && $fileId !== '') {
                        $current = $service->files->get($fileId, ['fields' => 'parents']);
                        $parents = $current ? $current->getParents() : null;
                        $prevParents = is_array($parents) ? implode(',', $parents) : '';
                        $service->files->update($fileId, new \Google_Service_Drive_DriveFile(), [
                            'addParents' => $trashFolderId,
                            'removeParents' => $prevParents,
                            'fields' => 'id, parents',
                        ]);
                        $moved = true;
                    }
                }
            } catch (\Throwable $e) {
                $moved = false;
            }
        } else {
            $rel = (string)($doc['local_path'] ?? '');
            if ($rel !== '') {
                $src = Storage::privatePath($rel);
                if (is_file($src)) {
                    $baseTrashRel = 'tenant_' . $tenantId . '/ged/members/_trash';
                    $legacyTrashRelDir = $baseTrashRel . '/member_' . $memberId;
                    $friendlyTrashRelDir = $baseTrashRel . '/member_' . $memberId . '_' . $memberSlug;

                    $legacyTrashDir = Storage::privatePath($legacyTrashRelDir);
                    $friendlyTrashDir = Storage::privatePath($friendlyTrashRelDir);
                    $trashDir = is_dir($legacyTrashDir) ? $legacyTrashDir : $friendlyTrashDir;
                    if (!is_dir($trashDir)) {
                        mkdir($trashDir, 0775, true);
                    }
                    $base = basename($src);
                    $destName = date('Ymd_His') . '_' . $id . '_' . $base;
                    $dest = $trashDir . DIRECTORY_SEPARATOR . $destName;
                    if (@rename($src, $dest)) {
                        $usedTrashRelDir = ($trashDir === $legacyTrashDir) ? $legacyTrashRelDir : $friendlyTrashRelDir;
                        $newLocalPath = $usedTrashRelDir . '/' . $destName;
                        $moved = true;
                    }
                }
            }
        }

        $stmt = $pdo->prepare(
            'UPDATE entity_documents
             SET deleted_at = NOW(),
                 deleted_by_user_id = :uid,
                 delete_reason = :reason,
                 local_path = COALESCE(:new_local_path, local_path)
             WHERE tenant_id = :tenant_id AND id = :id AND entity_type = "member" AND entity_id = :member_id AND deleted_at IS NULL'
        );
        $stmt->execute([
            'uid' => (int)($_SESSION['user_id'] ?? 0) ?: null,
            'reason' => ($reason !== '' ? $reason : null),
            'new_local_path' => $newLocalPath,
            'tenant_id' => $tenantId,
            'id' => $id,
            'member_id' => $memberId,
        ]);

        Session::flash('success', $moved ? 'Document déplacé en corbeille.' : 'Document supprimé (fichier non déplacé).');
        redirect('/members/edit?id=' . $memberId);
    }
}
