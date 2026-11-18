<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo;
    if (!$pdo instanceof PDO) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    return $pdo;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function text_length(string $value): int
{
    $length = preg_match_all('/./us', $value);
    if ($length === false) {
        throw new RuntimeException('O texto enviado tem uma codificação inválida.');
    }

    return $length;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        throw new RuntimeException('Sua sessão expirou. Atualize a página e tente novamente.');
    }
}

function redirect_home(): never
{
    header('Location: index.php');
    exit;
}

function set_flash(string $message, string $kind = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'kind' => $kind];
}

function get_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return is_array($flash) ? $flash : null;
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $statement = db()->prepare('SELECT id, name, email, balance, xp FROM users WHERE id = ?');
    $statement->execute([$_SESSION['user_id']]);
    $user = $statement->fetch();

    return $user ?: null;
}

function user_pet(int $userId): ?array
{
    $statement = db()->prepare('SELECT * FROM pets WHERE user_id = ? AND memorial_at IS NULL ORDER BY id DESC LIMIT 1');
    $statement->execute([$userId]);
    $pet = $statement->fetch();

    return $pet ?: null;
}

function clamp_stat(float $value): int
{
    return (int) max(0, min(100, round($value)));
}

function update_pet_time(PDO $pdo, array &$pet): void
{
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $last = new DateTimeImmutable($pet['last_updated'], new DateTimeZone('UTC'));
    $elapsedHours = min(168, max(0, ($now->getTimestamp() - $last->getTimestamp()) / 3600));
    if ($elapsedHours < 0.02) {
        return;
    }

    $speciesRates = [
        'Cão' => [1.0, 1.0, 1.2, 1.1, 1.0],
        'Gato' => [1.0, 0.9, 0.9, 0.75, 0.85],
        'Coelho' => [1.05, 1.05, 0.85, 0.8, 0.9],
        'Hamster' => [0.8, 0.85, 0.55, 0.55, 0.75],
        'Calopsita' => [0.75, 0.95, 0.6, 0.7, 0.85],
    ];
    [$hungerRate, $thirstRate, $hygieneRate, $energyRate, $happinessRate] = $speciesRates[$pet['species']] ?? [1, 1, 1, 1, 1];
    if ($pet['trait'] === 'Glutão') {
        $hungerRate *= 1.3;
    } elseif ($pet['trait'] === 'Brincalhão') {
        $energyRate *= 1.2;
    } elseif ($pet['trait'] === 'Preguiçoso') {
        $energyRate *= 0.7;
    } elseif ($pet['trait'] === 'Carinhoso') {
        $happinessRate *= 0.75;
    } elseif ($pet['trait'] === 'Medroso') {
        $happinessRate *= 1.15;
    } elseif ($pet['trait'] === 'Curioso') {
        $energyRate *= 1.08;
    } elseif ($pet['trait'] === 'Calminho') {
        $energyRate *= 0.85;
    }

    $hunger = clamp_stat((float) $pet['hunger'] - $elapsedHours * 3.2 * $hungerRate);
    $thirst = clamp_stat((float) $pet['thirst'] - $elapsedHours * 4.1 * $thirstRate);
    $hygiene = clamp_stat((float) $pet['hygiene'] - $elapsedHours * 1.15 * $hygieneRate);
    $energy = clamp_stat((float) $pet['energy'] - $elapsedHours * 0.75 * $energyRate);
    $happiness = clamp_stat((float) $pet['happiness'] - $elapsedHours * 0.55 * $happinessRate);
    $health = (int) $pet['health'];

    if ($hunger < 15 || $thirst < 15 || $hygiene < 10) {
        $health = clamp_stat($health - $elapsedHours * 0.7);
    }
    if ($hunger > 70 && $thirst > 70 && $hygiene > 60) {
        $health = clamp_stat($health + $elapsedHours * 0.08);
    }

    $statement = $pdo->prepare(
        'UPDATE pets SET hunger = ?, thirst = ?, hygiene = ?, energy = ?, happiness = ?, health = ?, last_updated = ? WHERE id = ?'
    );
    $statement->execute([
        $hunger,
        $thirst,
        $hygiene,
        $energy,
        $happiness,
        $health,
        $now->format('Y-m-d H:i:s'),
        $pet['id'],
    ]);

    $pet = array_merge($pet, [
        'hunger' => $hunger,
        'thirst' => $thirst,
        'hygiene' => $hygiene,
        'energy' => $energy,
        'happiness' => $happiness,
        'health' => $health,
        'last_updated' => $now->format('Y-m-d H:i:s'),
    ]);
}

function pet_stage(array $pet): string
{
    $ageMonths = pet_age_months($pet);
    if ($ageMonths < 12) {
        return 'Filhote';
    }
    if ($ageMonths < 84) {
        return 'Adulto';
    }

    function pet_age_months(array $pet): int
    {
        $created = new DateTimeImmutable($pet['created_at'], new DateTimeZone('UTC'));
        $days = (int) $created->diff(new DateTimeImmutable('now', new DateTimeZone('UTC')))->days;

        return (int) $pet['starting_age_months'] + $days;
    }

    function pet_lifespan_months(string $species): int
    {
        return [
            'Cão' => 144,
            'Gato' => 180,
            'Coelho' => 108,
            'Hamster' => 36,
            'Calopsita' => 240,
        ][$species] ?? 120;
    }

    return 'Sênior';
}

function inventory(PDO $pdo, int $userId): array
{
    $statement = $pdo->prepare('SELECT item_code, quantity FROM inventory WHERE user_id = ?');
    $statement->execute([$userId]);
    $items = [];
    foreach ($statement->fetchAll() as $row) {
        $items[$row['item_code']] = (int) $row['quantity'];
    }

    return $items;
}

function change_inventory(PDO $pdo, int $userId, string $itemCode, int $amount): void
{
    $statement = $pdo->prepare(
        'INSERT INTO inventory (user_id, item_code, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)'
    );
    $statement->execute([$userId, $itemCode, $amount]);
}

function consume_inventory(PDO $pdo, int $userId, string $itemCode, int $amount = 1): void
{
    $statement = $pdo->prepare('UPDATE inventory SET quantity = quantity - ? WHERE user_id = ? AND item_code = ? AND quantity >= ?');
    $statement->execute([$amount, $userId, $itemCode, $amount]);
    if ($statement->rowCount() !== 1) {
        throw new RuntimeException('Esse item acabou. Visite a lojinha para repor.');
    }
}

function change_balance(PDO $pdo, int $userId, int $amount): void
{
    $statement = $pdo->prepare('UPDATE users SET balance = balance + ? WHERE id = ? AND balance + ? >= 0');
    $statement->execute([$amount, $userId, $amount]);
    if ($statement->rowCount() !== 1) {
        throw new RuntimeException('Saldo insuficiente para essa ação.');
    }
}

function record_medical(PDO $pdo, int $petId, string $recordType, string $description, int $cost): void
{
    $statement = $pdo->prepare(
        'INSERT INTO medical_records (pet_id, record_type, description, cost) VALUES (?, ?, ?, ?)'
    );
    $statement->execute([$petId, $recordType, $description, $cost]);
}

function pet_update(PDO $pdo, array $pet, array $changes): array
{
    $allowed = ['hunger', 'thirst', 'hygiene', 'energy', 'health', 'happiness', 'confidence', 'obedience', 'home_cleanliness'];
    $sets = [];
    $values = [];
    foreach ($changes as $key => $value) {
        if (!in_array($key, $allowed, true)) {
            continue;
        }
        $sets[] = $key . ' = ?';
        $values[] = $key === 'confidence' || $key === 'obedience'
            ? clamp_stat((float) $value)
            : clamp_stat((float) $value);
        $pet[$key] = end($values);
    }
    if ($sets) {
        $values[] = $pet['id'];
        $pdo->prepare('UPDATE pets SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values);
    }

    return $pet;
}

function badge_for(string $species): string
{
    return [
        'Cão' => '🐕',
        'Gato' => '🐈',
        'Coelho' => '🐇',
        'Hamster' => '🐹',
        'Calopsita' => '🦜',
    ][$species] ?? '🐾';
}
