<?php
session_start();

/* ============================================
   CONFIG
============================================ */
$WORDS  = __DIR__ . '/words.txt';
$SCORES = __DIR__ . '/scores.json';

if (!file_exists($WORDS)) {
    die("words.txt missing");
}

$words = array_values(
    array_map(
        'strtoupper',
        array_filter(
            file($WORDS, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES),
            fn($w) => strlen(trim($w)) === 5
        )
    )
);

/* ============================================
   HELPERS
============================================ */
function json_load($path)
{
    if (!file_exists($path)) return [];
    $x = file_get_contents($path);
    return $x ? json_decode($x, true) : [];
}

function json_save($path, $v)
{
    file_put_contents($path, json_encode($v, JSON_PRETTY_PRINT));
}

function scores_sorted()
{
    $s = json_load(__DIR__ . '/scores.json');

    usort($s, function ($a, $b) {
        if ($a['score'] == 0 && $b['score'] != 0) return 1;
        if ($b['score'] == 0 && $a['score'] != 0) return -1;

        if ($a['attempts'] != $b['attempts']) {
            return ($a['attempts'] < $b['attempts']) ? -1 : 1;
        }

        return strtotime($b['date']) <=> strtotime($a['date']);
    });

    return $s;
}

function save_score($user, $score)
{
    $s = json_load(__DIR__ . '/scores.json');

    $s[] = [
        'username' => $user,
        'score'    => $score,
        'attempts' => $_SESSION['attempts'] ? count($_SESSION['attempts']) : 0,
        'date'     => date('c')
    ];

    json_save(__DIR__ . '/scores.json', $s);
}

/* ============================================
   LEADERBOARD PAGE (Direct Access)
============================================ */
if (isset($_GET['leaderboard'])) {
    $scores = scores_sorted();
    ?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Leaderboard</title>

    <style>
        body {
            font-family: Inter, Arial;
            background: #071018;
            color: #dfeaf2;
            padding: 30px;
        }
        table {
            width: 80%;
            margin: auto;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }
    </style>
</head>

<body>
    <h1 style="text-align:center">Leaderboard</h1>

    <table>
        <tr>
            <th>Player</th>
            <th>Score</th>
            <th>Attempts</th>
            <th>Date</th>
        </tr>

        <?php foreach ($scores as $r): 
            $label = ($r['score'] === 0 ? 'LOSS' : $r['score']);
            $d     = date('Y-m-d H:i', strtotime($r['date']));
        ?>
            <tr>
                <td><?= htmlspecialchars($r['username']) ?></td>
                <td><?= $label ?></td>
                <td><?= $r['attempts'] ?></td>
                <td><?= $d ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <p style="text-align:center;margin-top:12px">
        <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">Back</a>
    </p>
</body>
</html>
<?php
    exit;
}

/* ============================================
   LOGOUT
============================================ */
if (isset($_GET['clear'])) {
    session_unset();
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

/* ============================================
   PROCESS LOGIN
============================================ */
if (!isset($_SESSION['username']) && isset($_POST['username'])) {
    $_SESSION['username'] = trim($_POST['username']);
    $_SESSION['mode']     = $_POST['mode'] ?? 'Normal';
    $_SESSION['attempts'] = [];
    unset($_SESSION['game_over']);
    $_SESSION['target']   = $words[array_rand($words)];
}

/* ============================================
   NEW GAME
============================================ */
if (isset($_POST['newgame'])) {
    $_SESSION['attempts'] = [];
    unset($_SESSION['game_over']);

    $_SESSION['target'] = $words[array_rand($words)];
    $_SESSION['mode']   = $_POST['mode'] ?? ($_SESSION['mode'] ?? 'Normal');
}

/* ============================================
   GUESSING LOGIC
============================================ */
$message  = '';
$keyboard = [];

$mode = $_SESSION['mode'] ?? 'Normal';
$maxAttempts = ($mode === 'Easy' ? 8 : ($mode === 'Hard' ? 5 : 6));

if (
    isset($_SESSION['username']) &&
    isset($_POST['guess']) &&
    empty($_SESSION['game_over'])
) {
    $guess = strtoupper(trim($_POST['guess']));

    if (!preg_match('/^[A-Z]{5}$/', $guess)) {
        $message = 'Guess must be 5 letters';
    } elseif (!in_array($guess, $words)) {
        $message = 'Word not in list';
    } else {
        $target   = str_split($_SESSION['target']);
        $g        = str_split($guess);
        $feedback = array_fill(0, 5, 'gray');
        $rem      = [];

        // First pass: Greens
        for ($i = 0; $i < 5; $i++) {
            if ($g[$i] === $target[$i]) {
                $feedback[$i] = 'green';
            } else {
                $rem[$target[$i]] = ($rem[$target[$i]] ?? 0) + 1;
            }
        }

        // Second pass: Yellows
        for ($i = 0; $i < 5; $i++) {
            if ($feedback[$i] === 'green') continue;
            if (!empty($rem[$g[$i]])) {
                $feedback[$i] = 'yellow';
                $rem[$g[$i]]--;
            }
        }

        // Save attempt
        $_SESSION['attempts'][] = [
            'word'   => $guess,
            'colors' => $feedback
        ];

        // Update keyboard
        foreach ($_SESSION['attempts'] as $row) {
            foreach ($row['colors'] as $i => $c) {
                $ltr = $row['word'][$i];
                $prio = ['gray' => 1, 'yellow' => 2, 'green' => 3];

                if (!isset($keyboard[$ltr]) || $prio[$c] > $prio[$keyboard[$ltr]]) {
                    $keyboard[$ltr] = $c;
                }
            }
        }

        // Win
        if ($guess === $_SESSION['target']) {
            $_SESSION['game_over'] = true;
            $message = 'CORRECT! Word: ' . $_SESSION['target'];
            save_score($_SESSION['username'], count($_SESSION['attempts']));
        }

        // Lose
        elseif (count($_SESSION['attempts']) >= $maxAttempts) {
            $_SESSION['game_over'] = true;
            $message = 'YOU LOST! Word: ' . $_SESSION['target'];
            save_score($_SESSION['username'], 0);
        }
    }

} else {
    // Construct keyboard from past attempts
    foreach ($_SESSION['attempts'] ?? [] as $row) {
        foreach ($row['colors'] as $i => $c) {
            $ltr = $row['word'][$i];
            $prio = ['gray' => 1, 'yellow' => 2, 'green' => 3];

            if (!isset($keyboard[$ltr]) || $prio[$c] > $prio[$keyboard[$ltr]]) {
                $keyboard[$ltr] = $c;
            }
        }
    }
}

/* ============================================
   RENDER FUNCTIONS
============================================ */
function render_tile($char = '', $cls = '')
{
    $ch = htmlspecialchars($char);
    echo "<div class='tile $cls'>$ch</div>";
}

function render_keyboard($keyboard)
{
    $rows = ['QWERTYUIOP', 'ASDFGHJKL', 'ZXCVBNM'];

    foreach ($rows as $r) {
        echo "<div class='keyrow'>";
        foreach (str_split($r) as $k) {
            $c = $keyboard[$k] ?? '';
            $cls = $c ? "key $c" : "key";
            echo "<div class='$cls'>" . htmlspecialchars($k) . "</div>";
        }
        echo "</div>";
    }
}

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Wordle — Group 2</title>

    <style>
        /* ROOT COLORS */
        :root {
            --bg: #071018;
            --card: #0f171a;
            --muted: #9fb3bf;
            --accent: #16a34a;
        }

        /* GLOBAL */
        body {
            margin: 0;
            background: linear-gradient(#061018, #081217);
            color: var(--muted);
            font-family: Inter, Arial;
        }

        .container {
            display: flex;
            gap: 20px;
            padding: 22px;
            align-items: flex-start;
        }

        .left {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .panel {
            background: linear-gradient(180deg, #0f171a, #081014);
            padding: 18px;
            border-radius: 12px;
            min-width: 540px;
        }

        /* GRID */
        .grid {
            display: grid;
            grid-template-columns: repeat(5, 72px);
            gap: 12px;
            justify-content: center;
        }

        .tile {
            width: 72px;
            height: 72px;
            border-radius: 10px;
            background: #091114;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: #e8f3ff;
            border: 2px solid #0c1316;
        }

        .tile.green {
            background: #15803d;
            color: white;
            border-color: #0d3d25;
        }

        .tile.yellow {
            background: #f59f0b;
            color: #111;
            border-color: #4d3510;
        }

        .tile.gray {
            background: #39414a;
            color: white;
            border-color: #2b3137;
        }

        /* CONTROLS */
        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .input-row {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 10px;
        }

        input[type=text] {
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #152226;
            background: #061214;
            color: #eaf6ff;
            text-transform: uppercase;
            width: 200px;
        }

        .btn {
            padding: 8px 12px;
            border-radius: 8px;
            background: #0b5;
            cursor: pointer;
            border: 0;
            color: #042;
        }

        /* LEADERBOARD */
        .leaderboard {
            width: 300px;
        }

        .lb {
            background: #0b1620;
            padding: 12px;
            border-radius: 10px;
        }

        /* KEYBOARD */
        .keyrow {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin-bottom: 8px;
        }

        .key {
            min-width: 44px;
            height: 48px;
            border-radius: 8px;
            background: #071419;
            color: #bfe8ff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        .key.green { background: #15803d; color: #fff; }
        .key.yellow { background: #f59f0b; color: #111; }
        .key.gray   { background: #39414a; color: #fff; }

        .small {
            font-size: 13px;
            color: #9fb3bf;
        }

        a.link {
            color: #90caf9;
            text-decoration: none;
        }

        /* RESPONSIVE */
        @media(max-width: 900px) {
            .container {
                flex-direction: column;
                align-items: center;
            }

            .leaderboard {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<?php if (!isset($_SESSION['username'])): ?>

    <!-- LOGIN SCREEN -->
    <div style="display:flex;align-items:center;justify-content:center;padding:40px">
        <div class="panel" style="text-align:center">

            <h2 style="color:#fff">Welcome</h2>

            <form method="POST">
                <div style="margin:10px">
                    <input type="text" name="username" maxlength="20" placeholder="Your name" required>
                </div>

                <div style="margin:8px">
                    <select name="mode">
                        <option>Easy</option>
                        <option selected>Normal</option>
                        <option>Hard</option>
                    </select>
                </div>

                <div style="margin-top:12px">
                    <button class="btn" type="submit">Start</button>
                </div>
            </form>

            <div class="small" style="margin-top:10px">
                <a class="link" href="?leaderboard=1">View Leaderboard</a>
            </div>

        </div>
    </div>

<?php else: ?>

    <!-- MAIN GAME UI -->
    <div class="container">

        <div class="left">
            <div class="panel">

                <!-- TOP CONTROLS -->
                <div class="controls">

                    <div>
                        <strong style="color:#fff"><?= htmlspecialchars($_SESSION['username']) ?></strong>

                        <div class="small">
                            Mode: <?= htmlspecialchars($_SESSION['mode'] ?? 'Normal') ?> —
                            Attempts: <?= count($_SESSION['attempts'] ?? []) ?> / <?= $maxAttempts ?>
                        </div>
                    </div>

                    <div>
                        <form method="POST" style="display:inline">
                            <button class="btn" name="newgame" type="submit">New Game</button>
                        </form>

                        <a class="link" href="?clear=1" style="margin-left:8px;color:#90caf9">
                            Change User
                        </a>
                    </div>

                </div>

                <!-- LETTER GRID -->
                <div class="grid">
                    <?php
                    for ($r = 0; $r < $maxAttempts; $r++) {
                        if (isset($_SESSION['attempts'][$r])) {
                            $row = $_SESSION['attempts'][$r];

                            for ($i = 0; $i < 5; $i++) {
                                echo "<div class='tile {$row['colors'][$i]}'>"
                                     . $row['word'][$i] .
                                     "</div>";
                            }

                        } else {
                            for ($i = 0; $i < 5; $i++) {
                                echo "<div class='tile'></div>";
                            }
                        }
                    }
                    ?>
                </div>

                <!-- INPUT FIELD -->
                <?php if (empty($_SESSION['game_over'])): ?>
                    <form method="POST" class="input-row">
                        <input type="text"
                               name="guess"
                               maxlength="5"
                               pattern="[A-Za-z]{5}"
                               required
                               placeholder="Enter 5-letter word">

                        <button class="btn" type="submit">Enter</button>
                    </form>
                <?php endif; ?>

                <!-- MESSAGE -->
                <?php if (!empty($message)): ?>
                    <div style="margin-top:12px;color:#bfe8c9;font-weight:700;text-align:center">
                        <?= $message ?>
                    </div>
                <?php endif; ?>

                <!-- KEYBOARD -->
                <div style="margin-top:16px">
                    <?php render_keyboard($keyboard); ?>
                </div>

            </div>
        </div>

        <!-- LEADERBOARD (RIGHT PANEL) -->
        <aside class="leaderboard">
            <div class="lb">
                <h3 style="margin:0 0 8px;color:#fff">Leaderboard</h3>

                <table style="width:100%;border-collapse:collapse">
                    <?php
                    foreach (scores_sorted() as $row) {
                        $label = ($row['score'] === 0 ? 'LOSS' : $row['score']);
                        echo "<tr>
                                <td style='padding:6px'>" . htmlspecialchars($row['username']) . "</td>
                                <td style='padding:6px'>" . htmlspecialchars($label) . "</td>
                                <td style='padding:6px'>" . htmlspecialchars($row['attempts']) . "</td>
                              </tr>";
                    }
                    ?>
                </table>

                <div style="margin-top:10px">
                    <a class="link" href="?leaderboard=1">View full leaderboard</a>
                </div>
            </div>
        </aside>

    </div>

<?php endif; ?>

</body>
</html>
