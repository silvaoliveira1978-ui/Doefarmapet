<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$speciesOptions = [
    'Cão' => ['varieties' => ['Vira-lata', 'Pequeno porte', 'Médio porte', 'Grande porte']],
    'Gato' => ['varieties' => ['Pelo curto', 'Pelo longo', 'Siamês', 'Sem raça definida']],
    'Coelho' => ['varieties' => ['Mini lop', 'Lionhead', 'Holandês', 'Sem raça definida']],
    'Hamster' => ['varieties' => ['Sírio', 'Anão russo', 'Roborovski']],
    'Calopsita' => ['varieties' => ['Cara branca', 'Cinza', 'Lutino', 'Pérola']],
];
$traits = ['Brincalhão', 'Carinhoso', 'Medroso', 'Curioso', 'Calminho', 'Glutão', 'Preguiçoso'];
$coats = ['Caramelo', 'Branquinho', 'Pretinho', 'Cinza', 'Mesclado'];
$products = [
    'food_standard' => ['name' => 'Ração equilibrada', 'price' => 18, 'quantity' => 5, 'icon' => '🥣', 'description' => '5 porções para o dia a dia'],
    'food_premium' => ['name' => 'Ração premium', 'price' => 30, 'quantity' => 5, 'icon' => '✨', 'description' => '5 porções nutritivas'],
    'food_senior' => ['name' => 'Ração especial', 'price' => 34, 'quantity' => 5, 'icon' => '🌿', 'description' => '5 porções para pets sênior'],
    'toy' => ['name' => 'Brinquedo novo', 'price' => 25, 'quantity' => 3, 'icon' => '🎾', 'description' => '3 sessões de brincadeira'],
    'brush' => ['name' => 'Escova de cuidados', 'price' => 20, 'quantity' => 1, 'icon' => '🪮', 'description' => 'Ajuda a manter a higiene em dia'],
    'bed' => ['name' => 'Caminha aconchegante', 'price' => 55, 'quantity' => 1, 'icon' => '🛏️', 'description' => 'Descanso confortável para seu pet'],
];

function render_stat(string $label, int $value, string $icon): void
{
    $state = $value < 25 ? 'low' : ($value < 55 ? 'medium' : 'good');
    echo '<div class="stat-row"><div class="stat-label"><span>' . $icon . ' ' . e($label) . '</span><strong>' . $value . '%</strong></div>';
    echo '<div class="meter"><span class="meter-fill ' . $state . '" style="width:' . $value . '%"></span></div></div>';
}

$error = '';
try {
    $pdo = db();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'register') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $password = (string) ($_POST['password'] ?? '');
            if ($name === '' || text_length($name) > 80 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
                throw new RuntimeException('Confira seus dados. A senha precisa ter pelo menos 8 caracteres.');
            }
            $pdo->beginTransaction();
            $statement = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
            $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $userId = (int) $pdo->lastInsertId();
            change_inventory($pdo, $userId, 'food_standard', 5);
            change_inventory($pdo, $userId, 'toy', 3);
            change_inventory($pdo, $userId, 'brush', 1);
            change_inventory($pdo, $userId, 'bed', 1);
            $pdo->commit();
            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            set_flash('Conta criada! Agora escolha seu novo companheiro.');
            redirect_home();
        }

        if ($action === 'login') {
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $statement = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = ?');
            $statement->execute([$email]);
            $account = $statement->fetch();
            if (!$account || !password_verify((string) ($_POST['password'] ?? ''), $account['password_hash'])) {
                throw new RuntimeException('E-mail ou senha incorretos.');
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $account['id'];
            redirect_home();
        }

        if ($action === 'logout') {
            $_SESSION = [];
            session_destroy();
            redirect_home();
        }

        $user = current_user();
        if (!$user) {
            throw new RuntimeException('Entre na sua conta para continuar.');
        }
        $userId = (int) $user['id'];

        if ($action === 'adopt') {
            if (user_pet($userId)) {
                throw new RuntimeException('Você já tem um pet esperando por seus cuidados.');
            }
            $petName = trim((string) ($_POST['pet_name'] ?? ''));
            $species = (string) ($_POST['species'] ?? '');
            $variety = (string) ($_POST['variety'] ?? '');
            $trait = (string) ($_POST['trait'] ?? '');
            $coat = (string) ($_POST['coat'] ?? '');
            if ($petName === '' || text_length($petName) > 40 || !isset($speciesOptions[$species])
                || !in_array($variety, $speciesOptions[$species]['varieties'], true)
                || !in_array($trait, $traits, true) || !in_array($coat, $coats, true)) {
                throw new RuntimeException('Revise as opções de adoção e tente novamente.');
            }
            $statement = $pdo->prepare(
                'INSERT INTO pets (user_id, name, species, variety, coat, trait) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([$userId, $petName, $species, $variety, $coat, $trait]);
            set_flash('A amizade de vocês começou. Cuide bem de ' . $petName . '!');
            redirect_home();
        }

        $pdo->beginTransaction();
        $statement = $pdo->prepare('SELECT * FROM pets WHERE user_id = ? AND memorial_at IS NULL ORDER BY id DESC LIMIT 1 FOR UPDATE');
        $statement->execute([$userId]);
        $pet = $statement->fetch();
        if (!$pet) {
            throw new RuntimeException('Adote um pet antes de escolher uma atividade.');
        }
        if (pet_age_months($pet) >= pet_lifespan_months($pet['species'])) {
            $pdo->prepare('UPDATE pets SET memorial_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$pet['id']]);
            $pdo->prepare(
                'INSERT INTO medical_records (pet_id, record_type, description, cost) VALUES (?, ?, ?, 0)'
            )->execute([$pet['id'], 'Memorial', 'Uma vida cheia de carinho e boas lembranças']);
            $pdo->commit();
            set_flash('Uma linda jornada chegou ao fim. As lembranças de ' . $pet['name'] . ' ficam com você.');
            redirect_home();
        }
        update_pet_time($pdo, $pet);

        switch ($action) {
            case 'feed':
                $food = (string) ($_POST['food'] ?? '');
                $foodStats = [
                    'food_standard' => [26, 0],
                    'food_premium' => [32, 2],
                    'food_senior' => [30, 3],
                ];
                if (!isset($foodStats[$food])) {
                    throw new RuntimeException('Escolha uma ração disponível.');
                }
                consume_inventory($pdo, $userId, $food);
                $pet = pet_update($pdo, $pet, [
                    'hunger' => (int) $pet['hunger'] + $foodStats[$food][0],
                    'health' => (int) $pet['health'] + $foodStats[$food][1],
                    'happiness' => (int) $pet['happiness'] + 2,
                ]);
                set_flash($pet['name'] . ' adorou a refeição!');
                break;
            case 'water':
                $pet = pet_update($pdo, $pet, ['thirst' => (int) $pet['thirst'] + 38]);
                set_flash('Água fresquinha servida. 💧');
                break;
            case 'affection':
                $pet = pet_update($pdo, $pet, [
                    'happiness' => (int) $pet['happiness'] + 18,
                    'confidence' => (int) $pet['confidence'] + 5,
                ]);
                set_flash('Um momento de carinho fortaleceu a confiança de vocês.');
                break;
            case 'sleep':
                if ((int) (inventory($pdo, $userId)['bed'] ?? 0) < 1) {
                    throw new RuntimeException('Compre uma caminha para seu pet descansar.');
                }
                $pet = pet_update($pdo, $pet, ['energy' => (int) $pet['energy'] + 38]);
                set_flash($pet['name'] . ' tirou uma boa soneca. 💤');
                break;
            case 'clean':
                $availableItems = inventory($pdo, $userId);
                if ((int) ($availableItems['brush'] ?? 0) < 1) {
                    throw new RuntimeException('Compre uma escova na lojinha para cuidar da higiene.');
                }
                $pet = pet_update($pdo, $pet, [
                    'hygiene' => (int) $pet['hygiene'] + 30,
                    'home_cleanliness' => (int) $pet['home_cleanliness'] + 22,
                ]);
                set_flash('Ambiente organizado e pet limpinho!');
                break;
            case 'play':
                consume_inventory($pdo, $userId, 'toy');
                $pet = pet_update($pdo, $pet, [
                    'happiness' => (int) $pet['happiness'] + 22,
                    'energy' => (int) $pet['energy'] - 12,
                    'hunger' => (int) $pet['hunger'] - 5,
                ]);
                set_flash('A brincadeira rendeu bons momentos! 🎾');
                break;
            case 'walk':
                if ($pet['species'] !== 'Cão') {
                    throw new RuntimeException('Passeios na rua estão disponíveis para cães. Escolha brincadeiras para seu pet.');
                }
                $pet = pet_update($pdo, $pet, [
                    'happiness' => (int) $pet['happiness'] + 20,
                    'energy' => (int) $pet['energy'] - 20,
                    'hunger' => (int) $pet['hunger'] - 8,
                    'hygiene' => (int) $pet['hygiene'] - 5,
                ]);
                set_flash('Passeio concluído! Foi bom explorar o bairro juntos.');
                break;
            case 'train':
                if ((int) $pet['energy'] < 15) {
                    throw new RuntimeException($pet['name'] . ' precisa descansar antes de treinar.');
                }
                $pet = pet_update($pdo, $pet, [
                    'obedience' => (int) $pet['obedience'] + 8,
                    'confidence' => (int) $pet['confidence'] + 4,
                    'energy' => (int) $pet['energy'] - 8,
                    'happiness' => (int) $pet['happiness'] + 3,
                ]);
                set_flash('Treino com reforço positivo concluído. Muito bem!');
                break;
            case 'work':
                $statement = $pdo->prepare('SELECT last_work_at FROM users WHERE id = ? FOR UPDATE');
                $statement->execute([$userId]);
                $lastWork = $statement->fetchColumn();
                if ($lastWork && strtotime((string) $lastWork) > time() - 1800) {
                    $remainingMinutes = (int) ceil((1800 - (time() - strtotime((string) $lastWork))) / 60);
                    throw new RuntimeException('Você já trabalhou recentemente. Descanse e volte em ' . $remainingMinutes . ' min.');
                }
                $earned = random_int(35, 55);
                change_balance($pdo, $userId, $earned);
                $pdo->prepare('UPDATE users SET last_work_at = UTC_TIMESTAMP(), xp = xp + 10 WHERE id = ?')->execute([$userId]);
                $pet = pet_update($pdo, $pet, ['energy' => (int) $pet['energy'] - 5]);
                set_flash('Trabalho concluído: +' . $earned . ' moedas. Assim você mantém os cuidados em dia!');
                break;
            case 'vet':
                $cost = 65;
                change_balance($pdo, $userId, -$cost);
                $pet = pet_update($pdo, $pet, ['health' => (int) $pet['health'] + 35]);
                record_medical($pdo, (int) $pet['id'], 'Consulta', 'Consulta veterinária e avaliação geral', $cost);
                set_flash('Consulta realizada. A saúde de ' . $pet['name'] . ' melhorou.');
                break;
            case 'vaccinate':
                $cost = 45;
                change_balance($pdo, $userId, -$cost);
                $until = (new DateTimeImmutable('+1 year', new DateTimeZone('UTC')))->format('Y-m-d');
                $pdo->prepare('UPDATE pets SET vaccinated_until = ? WHERE id = ?')->execute([$until, $pet['id']]);
                record_medical($pdo, (int) $pet['id'], 'Vacina', 'Vacinação anual em dia', $cost);
                set_flash('Vacinação registrada até ' . date('d/m/Y', strtotime($until)) . '.');
                break;
            case 'neuter':
                if (pet_age_months($pet) < 6) {
                    throw new RuntimeException('A castração fica disponível quando seu pet for jovem.');
                }
                if ((int) $pet['neutered'] === 1) {
                    throw new RuntimeException('A castração já está registrada.');
                }
                $cost = 120;
                change_balance($pdo, $userId, -$cost);
                $pdo->prepare('UPDATE pets SET neutered = 1 WHERE id = ?')->execute([$pet['id']]);
                record_medical($pdo, (int) $pet['id'], 'Procedimento', 'Castração registrada', $cost);
                set_flash('Castração registrada no histórico de saúde.');
                break;
            case 'buy':
                $product = (string) ($_POST['product'] ?? '');
                if (!isset($products[$product])) {
                    throw new RuntimeException('Esse item não está disponível na lojinha.');
                }
                change_balance($pdo, $userId, -$products[$product]['price']);
                change_inventory($pdo, $userId, $product, $products[$product]['quantity']);
                set_flash($products[$product]['name'] . ' adicionado ao inventário.');
                break;
            default:
                throw new RuntimeException('Ação desconhecida. Atualize a página e tente novamente.');
        }
        if ($action !== 'work') {
            $pdo->prepare('UPDATE users SET xp = xp + 3 WHERE id = ?')->execute([$userId]);
        }
        $pdo->commit();
        redirect_home();
    }

    $user = current_user();
    $pet = $user ? user_pet((int) $user['id']) : null;
    $memorial = null;
    if ($user && $pet && pet_age_months($pet) >= pet_lifespan_months($pet['species'])) {
        $pdo->prepare('UPDATE pets SET memorial_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$pet['id']]);
        $pdo->prepare(
            'INSERT INTO medical_records (pet_id, record_type, description, cost) VALUES (?, ?, ?, 0)'
        )->execute([$pet['id'], 'Memorial', 'Uma vida cheia de carinho e boas lembranças',]);
        set_flash('Uma linda jornada chegou ao fim. As lembranças de ' . $pet['name'] . ' ficam com você.');
        $pet = null;
    }
    if ($user && !$pet) {
        $statement = $pdo->prepare('SELECT * FROM pets WHERE user_id = ? AND memorial_at IS NOT NULL ORDER BY memorial_at DESC LIMIT 1');
        $statement->execute([$user['id']]);
        $memorial = $statement->fetch() ?: null;
    }
    if ($user && $pet) {
        update_pet_time($pdo, $pet);
        $user = current_user();
    }
    $items = $user ? inventory($pdo, (int) $user['id']) : [];
    $records = [];
    if ($pet) {
        $statement = $pdo->prepare('SELECT record_type, description, cost, created_at FROM medical_records WHERE pet_id = ? ORDER BY created_at DESC LIMIT 4');
        $statement->execute([$pet['id']]);
        $records = $statement->fetchAll();
    }
    $flash = get_flash();
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        set_flash($exception instanceof PDOException
            ? 'Não foi possível concluir a operação. Confira a configuração do banco de dados.'
            : $exception->getMessage(), 'error');
        redirect_home();
    }
    $error = $exception instanceof PDOException
        ? 'Não foi possível conectar ao banco. Confira os dados em config.php e importe database.sql.'
        : 'Ocorreu um erro ao carregar o jogo. Tente novamente.';
    $user = null;
    $pet = null;
    $items = [];
    $records = [];
    $flash = null;
}

$stage = $pet ? pet_stage($pet) : '';
$speciesEmoji = $pet ? badge_for($pet['species']) : '🐾';
$vaccineDue = $pet && (!$pet['vaccinated_until'] || $pet['vaccinated_until'] < gmdate('Y-m-d'));
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f5f2ea">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="manifest" href="manifest.json">
    <link rel="icon" href="assets/pet-icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="assets/pet-icon.svg">
    <title>Adote Certo — seu cantinho pet</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php"><span class="brand-mark">🐾</span> adote<span>certo</span></a>
    <?php if (!empty($user)): ?>
        <div class="topbar-right">
            <span class="wallet">🪙 <?= (int) $user['balance'] ?> moedas</span>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="logout">
                <button class="link-button" type="submit">Sair</button>
            </form>
        </div>
    <?php else: ?>
        <span class="top-note">Um jogo sobre cuidar com amor</span>
    <?php endif; ?>
</header>

<main class="page-shell">
    <?php if ($error !== ''): ?>
        <div class="notice error"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($flash): ?>
        <div class="notice <?= e($flash['kind']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <?php if (empty($user)): ?>
        <section class="welcome-layout">
            <div class="welcome-copy">
                <div class="eyebrow">JOGO DE CUIDADO VIRTUAL</div>
                <h1>Uma amizade<br><em>para cuidar.</em></h1>
                <p>Adote um companheiro, conheça sua personalidade e descubra como pequenos cuidados fazem toda a diferença.</p>
                <div class="welcome-pet" aria-hidden="true">
                    <span class="sun">☀️</span><span class="welcome-animal">🐶</span>
                    <span class="welcome-caption">seu novo melhor amigo está esperando</span>
                </div>
            </div>
            <div class="auth-card">
                <div class="card-heading">
                    <span class="eyebrow">SEU CANTINHO PET</span>
                    <h2>Entre para jogar</h2>
                    <p>Crie sua conta ou continue sua aventura.</p>
                </div>
                <div class="auth-tabs" role="tablist">
                    <button class="auth-tab active" data-tab="login" type="button">Entrar</button>
                    <button class="auth-tab" data-tab="register" type="button">Criar conta</button>
                </div>
                <form class="auth-form active" data-form="login" method="post">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="login">
                    <label>E-mail<input type="email" name="email" autocomplete="email" required></label>
                    <label>Senha<input type="password" name="password" autocomplete="current-password" required></label>
                    <button class="button primary wide" type="submit">Continuar <span>→</span></button>
                </form>
                <form class="auth-form" data-form="register" method="post">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="register">
                    <label>Como podemos te chamar?<input type="text" name="name" maxlength="80" autocomplete="name" required></label>
                    <label>E-mail<input type="email" name="email" autocomplete="email" required></label>
                    <label>Crie uma senha <small>(mínimo 8 caracteres)</small><input type="password" name="password" minlength="8" autocomplete="new-password" required></label>
                    <button class="button primary wide" type="submit">Começar a aventura <span>→</span></button>
                </form>
                <div class="auth-foot">🌱 Cuidar também é aprender.</div>
            </div>
        </section>

    <?php elseif (!$pet): ?>
        <section class="adoption-layout">
            <div class="adoption-intro">
                <div class="eyebrow">ADOÇÃO RESPONSÁVEL · 01</div>
                <h1>Quem vai fazer<br><em>parte da família?</em></h1>
                <p>Todo pet tem seu jeitinho. Escolha seu novo companheiro e prepare-se para uma amizade cheia de cuidado.</p>
                <div class="shelter-scene" aria-hidden="true">
                    <span class="shelter-cloud">☁️</span>
                    <span class="shelter-pets">🐕 🐈 🐇</span>
                    <span class="shelter-ground"></span>
                </div>
            </div>
            <form class="adoption-card" method="post" id="adoption-form">
                <?php if ($memorial): ?>
                    <div class="memorial-card">
                        <span class="memorial-icon"><?= e(badge_for($memorial['species'])) ?></span>
                        <div><div class="eyebrow">MEMORIAL DE <?= e(strtoupper($memorial['name'])) ?></div><strong>Uma amizade para sempre.</strong><p><?= e($memorial['name']) ?> deixou lembranças bonitas. Quando se sentir pronto, outra adoção responsável pode começar.</p></div>
                    </div>
                <?php endif; ?>
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="adopt">
                <div class="section-title"><span class="step-number">1</span><div><h2>Seu novo amigo</h2><p>As escolhas podem ser simples — o carinho é o que importa.</p></div></div>
                <label>Nome do pet<input type="text" name="pet_name" maxlength="40" placeholder="Ex.: Pipoca" required></label>
                <div class="form-grid">
                    <label>Espécie
                        <select name="species" id="species-select">
                            <?php foreach ($speciesOptions as $species => $data): ?>
                                <option value="<?= e($species) ?>"><?= e($species) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Variedade
                        <select name="variety" id="variety-select"></select>
                    </label>
                </div>
                <div class="form-grid">
                    <label>Personalidade<select name="trait"><?php foreach ($traits as $trait): ?><option><?= e($trait) ?></option><?php endforeach; ?></select></label>
                    <label>Pelagem<select name="coat"><?php foreach ($coats as $coat): ?><option><?= e($coat) ?></option><?php endforeach; ?></select></label>
                </div>
                <div class="starter-kit"><span>🎁</span><div><strong>Kit de boas-vindas incluído</strong><small>5 porções de ração, brinquedo, escova e caminha. Você começa com 300 moedas.</small></div></div>
                <button class="button primary wide" type="submit">Confirmar adoção <span>→</span></button>
            </form>
        </section>

    <?php else: ?>
        <section class="game-heading">
            <div><div class="eyebrow">SEU CANTINHO PET</div><h1>Oi, <?= e(explode(' ', trim($user['name']))[0]) ?> <span>👋</span></h1><p>Um pouquinho de cuidado hoje faz uma grande diferença.</p></div>
            <div class="level-pill"><span>🌿</span><div><strong>Tutor nível <?= 1 + (int) floor((int) $user['xp'] / 100) ?></strong><small><?= (int) $user['xp'] ?> pontos de carinho</small></div></div>
        </section>
        <section class="game-grid">
            <div class="left-column">
                <article class="pet-scene-card">
                    <div class="scene-top"><span class="scene-label">EM CASA · <?= e(strtoupper($stage)) ?></span><span class="weather">☀️ Dia tranquilo</span></div>
                    <div class="room-scene">
                        <div class="room-window"><span>☀</span><i></i><i></i></div>
                        <span class="plant">🪴</span>
                        <div class="pet-stage">
                            <span class="pet-shadow"></span>
                            <span class="pet-emoji"><?= e($speciesEmoji) ?></span>
                            <span class="pet-heart">♥</span>
                        </div>
                        <div class="pet-nameplate"><strong><?= e($pet['name']) ?></strong><span><?= e($pet['trait']) ?> · <?= e($pet['coat']) ?></span></div>
                        <div class="room-floor"></div>
                        <div class="room-rug"></div>
                        <span class="room-bowl">🥣</span>
                    </div>
                    <div class="pet-meta">
                        <span><?= e($pet['variety']) ?></span><span class="meta-dot"></span>
                        <span><?= e($stage) ?> · <?= pet_age_months($pet) ?> meses</span><span class="meta-dot"></span>
                        <span>Confiança <?= (int) $pet['confidence'] ?>%</span>
                    </div>
                </article>
                <article class="panel care-panel">
                    <div class="panel-heading"><div><div class="eyebrow">ROTINA DE CUIDADOS</div><h2>Como está <?= e($pet['name']) ?>?</h2></div><span class="live-dot">● atualizado agora</span></div>
                    <div class="stats-grid">
                        <?php render_stat('Fome', (int) $pet['hunger'], '🍽️'); ?>
                        <?php render_stat('Sede', (int) $pet['thirst'], '💧'); ?>
                        <?php render_stat('Energia', (int) $pet['energy'], '⚡'); ?>
                        <?php render_stat('Felicidade', (int) $pet['happiness'], '💛'); ?>
                        <?php render_stat('Higiene', (int) $pet['hygiene'], '🫧'); ?>
                        <?php render_stat('Saúde', (int) $pet['health'], '💚'); ?>
                    </div>
                    <div class="home-clean"><span>🏡 Limpeza do ambiente</span><strong><?= (int) $pet['home_cleanliness'] ?>%</strong><span class="mini-meter"><i style="width:<?= (int) $pet['home_cleanliness'] ?>%"></i></span></div>
                </article>
                <article class="panel actions-panel">
                    <div class="panel-heading"><div><div class="eyebrow">O QUE VAMOS FAZER?</div><h2>Um cuidado de cada vez</h2></div></div>
                    <div class="action-grid">
                        <form method="post" class="action-item">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="feed">
                            <select class="action-select" name="food" aria-label="Tipo de ração">
                                <option value="food_standard">Ração equilibrada · <?= (int) ($items['food_standard'] ?? 0) ?></option>
                                <option value="food_premium">Ração premium · <?= (int) ($items['food_premium'] ?? 0) ?></option>
                                <option value="food_senior">Ração especial · <?= (int) ($items['food_senior'] ?? 0) ?></option>
                            </select><button class="action-button" type="submit"><span>🍲</span><strong>Alimentar</strong><small>Uma refeição gostosa</small></button>
                        </form>
                        <form method="post" class="action-item">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="water">
                            <button class="action-button" type="submit"><span>🚰</span><strong>Dar água</strong><small>Água fresca sempre</small></button>
                        </form>
                        <form method="post" class="action-item">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="affection">
                            <button class="action-button" type="submit"><span>🤍</span><strong>Fazer carinho</strong><small>Fortalece a confiança</small></button>
                        </form>
                        <form method="post" class="action-item">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="sleep">
                            <button class="action-button" type="submit"><span>🌙</span><strong>Hora da soneca</strong><small>Recupera energia</small></button>
                        </form>
                        <form method="post" class="action-item">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="clean">
                            <button class="action-button" type="submit"><span>🧼</span><strong>Dar uma geral</strong><small>Higiene e ambiente</small></button>
                        </form>
                        <form method="post" class="action-item">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="play">
                            <button class="action-button" type="submit"><span>🧸</span><strong>Brincar</strong><small>Gasta 1 brinquedo</small></button>
                        </form>
                        <?php if ($pet['species'] === 'Cão'): ?>
                            <form method="post" class="action-item">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="walk">
                                <button class="action-button" type="submit"><span>🦮</span><strong>Passear</strong><small>Explorar o bairro</small></button>
                            </form>
                        <?php endif; ?>
                        <form method="post" class="action-item">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="train">
                            <button class="action-button" type="submit"><span>🌟</span><strong>Treinar</strong><small>Reforço positivo</small></button>
                        </form>
                    </div>
                </article>
            </div>
            <aside class="right-column">
                <article class="panel shop-panel">
                    <div class="panel-heading"><div><div class="eyebrow">LOJINHA DO BAIRRO</div><h2>Um mimo ou cuidado?</h2></div><span class="coin-icon">🪙</span></div>
                    <div class="shop-list">
                        <?php foreach ($products as $code => $product): ?>
                            <div class="shop-item">
                                <span class="product-icon"><?= $product['icon'] ?></span>
                                <div class="product-info"><strong><?= e($product['name']) ?></strong><small><?= e($product['description']) ?><?php if (!empty($items[$code])): ?> · Você tem <?= (int) $items[$code] ?><?php endif; ?></small></div>
                                <form method="post">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="buy"><input type="hidden" name="product" value="<?= e($code) ?>">
                                    <button class="price-button" type="submit"><?= (int) $product['price'] ?> 🪙</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="shop-footer">💡 Dica: você ganha moedas trabalhando.</div>
                </article>
                <article class="work-card">
                    <div class="work-illustration">💼</div>
                    <div><div class="eyebrow">CUIDAR TAMBÉM É PLANEJAR</div><h3>Hora de trabalhar?</h3><p>Ganhe moedas para manter o estoque e os cuidados.</p></div>
                    <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="work"><button class="button dark wide" type="submit">Fazer um trabalho <span>→</span></button></form>
                    <small class="cooldown-note">Disponível a cada 30 minutos · seu pet descansa enquanto isso</small>
                </article>
                <article class="panel health-panel">
                    <div class="panel-heading"><div><div class="eyebrow">SAÚDE EM DIA</div><h2>Clínica veterinária</h2></div><span>🩺</span></div>
                    <div class="vaccine-status <?= $vaccineDue ? 'due' : '' ?>">
                        <span><?= $vaccineDue ? '🟠' : '✅' ?></span>
                        <div><strong><?= $vaccineDue ? 'Vacinação pendente' : 'Vacinação em dia' ?></strong><small><?= $vaccineDue ? 'Proteção anual importante' : 'Próxima: ' . date('d/m/Y', strtotime($pet['vaccinated_until'])) ?></small></div>
                    </div>
                    <div class="vet-actions">
                        <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="vet"><button class="button outline wide" type="submit">Consulta · 65 🪙</button></form>
                        <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="vaccinate"><button class="button outline wide" type="submit">Vacinar · 45 🪙</button></form>
                        <?php if ((int) $pet['neutered'] === 0): ?>
                            <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="neuter"><button class="button outline wide" type="submit">Castração · 120 🪙</button></form>
                        <?php else: ?>
                            <span class="procedure-done">✓ Castração registrada</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($records): ?>
                        <div class="records"><strong>Histórico recente</strong>
                            <?php foreach ($records as $record): ?>
                                <div class="record-row"><span><?= e($record['record_type']) ?> · <?= e($record['description']) ?></span><small><?= date('d/m', strtotime($record['created_at'])) ?></small></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
                <article class="tip-card"><span>🌼</span><div><strong>Posse responsável</strong><p>Na vida real, cada espécie tem necessidades diferentes. Consulte um veterinário para orientações sobre saúde e alimentação.</p></div></article>
            </aside>
        </section>
        <footer class="game-footer">Feito para aprender a cuidar com carinho <span>♥</span></footer>
    <?php endif; ?>
</main>
<script>
window.ADOTE_VARIETIES = <?= json_encode(
    array_map(static fn(array $item): array => $item['varieties'], $speciesOptions),
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;
</script>
<script src="app.js" defer></script>
</body>
</html>
