<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
boot_session();

$section = preg_replace('/[^a-z_]/', '', $_GET['section'] ?? 'overview');

/* ---------------- baza qurulmayıbsa ---------------- */
try {
    pdo()->query('SELECT 1 FROM admins LIMIT 1');
} catch (Throwable $e) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<meta charset="utf-8"><style>body{font-family:Montserrat,Arial,sans-serif;max-width:640px;margin:80px auto;padding:0 20px;line-height:1.6;color:#0f1c26}'
       . 'code{background:#eef2f4;padding:2px 8px;border-radius:6px}a{color:#0f8a4f}</style>'
       . '<h2>Panel hələ quraşdırılmayıb</h2>'
       . '<p>Bazanı yaratmaq və saytın məzmununu köçürmək üçün bir dəfə açın:</p>'
       . '<p><code>install.php?token=&lt;install_token&gt;</code></p>'
       . '<p class="muted">Açar <code>admin/config.php</code> faylındadır — onu heç vaxt ekranda göstərmirik.</p>';
    exit;
}

/* ---------------- logout ---------------- */
if ($section === 'logout') { logout(); redirect('index.php?section=login'); }

/* ---------------- login ---------------- */
if ($section === 'login') {
    if (current_admin()) redirect('index.php');
    $err = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_check();
        require_once __DIR__ . '/turnstile.php';
        $wait = login_blocked();
        if ($wait > 0) {
            $err = 'Слишком много попыток входа. Повторите через ' . ceil($wait / 60) . ' мин.';
        } elseif (!turnstile_verify($_POST['cf-turnstile-response'] ?? '')) {
            $err = 'Подтвердите, что вы не робот.';
        } elseif (attempt_login((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''))) {
            redirect('index.php');
        } else {
            $err = 'Неверный e-mail или пароль.';
        }
    }
    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Вход — Caucasus CL admin</title>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">';
    echo '<style>' . admin_css() . '</style></head><body>';
    echo '<div class="login"><form class="box" method="post">';
    echo '<div style="margin-bottom:12px"><img src="../assets/img/Caucasus-1.svg" alt="Caucasus"></div>';
    echo '<h2>Вход в панель</h2><div class="sub">Управление контентом сайта</div>';
    if ($err) echo '<div class="flash" style="background:#b91c1c">' . e($err) . '</div>';
    echo csrf_field();
    echo '<label>E-mail</label><input type="email" name="email" required autofocus>';
    echo '<label>Пароль</label><input type="password" name="password" required>';
    require_once __DIR__ . '/turnstile.php';
    echo '<div style="margin-top:14px;display:flex;justify-content:center">'; turnstile_widget(); echo '</div>';
    echo '<button class="btn" style="width:100%;margin-top:18px" type="submit">Войти</button>';
    echo '</form></div></body></html>';
    exit;
}

/* ---------------- auth ---------------- */
require_login();
if (!isset(SECTIONS[$section])) $section = 'overview';
if (in_array($section, ADMIN_ONLY, true) && !is_admin()) {
    flash('Недостаточно прав (нужна роль Администратор).', 'err');
    redirect('index.php?section=overview');
}
csrf_check();
$db = pdo();
$L  = cur_lang();   // redaktə olunan dil

/* ================== POST ================== */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $act = $_POST['action'] ?? '';

    /* --- sadə sətir cədvəlləri: hamısını birdən saxla --- */
    $bulk = [
        'clients' => ['image', 'name', 'url'],
        'links'   => ['title', 'url', 'descr'],
        'stats'   => ['num', 'suffix', 'label'],
        'process' => ['num', 'title', 'descr'],
        'whyus'   => ['title', 'descr'],
    ];
    $bulkKey = ['clients' => 'image', 'links' => 'title', 'stats' => 'label', 'process' => 'title', 'whyus' => 'title'];

    $saveBulk = function (string $table) use ($db, $bulk, $bulkKey, $L) {
        $cols = $bulk[$table];
        $key  = $bulkKey[$table];
        foreach (($_POST['r'] ?? []) as $i => $row) {
            $id  = (int)($row['id'] ?? 0);
            $val = trim((string)($row[$key] ?? ''));
            if ($id && $val === '') { $db->prepare("DELETE FROM `$table` WHERE id=?")->execute([$id]); continue; }
            if ($val === '') continue;
            $vals = [];
            foreach ($cols as $c) $vals[] = trim((string)($row[$c] ?? ''));
            $vals[] = (int)$i;
            if ($id) {
                $set = implode(',', array_map(fn($c) => "`$c`=?", array_merge($cols, ['sort'])));
                $db->prepare("UPDATE `$table` SET $set WHERE id=?")->execute([...$vals, $id]);
            } elseif ($table === 'clients') {
                // clients cədvəlində lang sütunu yoxdur — loqolar bütün dillərdə ortaqdır
                $cl = '`' . implode('`,`', array_merge($cols, ['sort'])) . '`';
                $ph = implode(',', array_fill(0, count($cols) + 1, '?'));
                $db->prepare("INSERT INTO `clients` ($cl) VALUES ($ph)")->execute($vals);
            } else {
                $cl = '`' . implode('`,`', array_merge($cols, ['sort', 'lang'])) . '`';
                $ph = implode(',', array_fill(0, count($cols) + 2, '?'));
                $db->prepare("INSERT INTO `$table` ($cl) VALUES ($ph)")->execute([...$vals, $L]);
            }
        }
    };

    /* --- Klientlər: sətir-sətir yükləmə və silmə --- */
    if ($section === 'clients' && $act === 'save_clients') {
        $added = $removed = 0;
        $bad = [];

        /* Yalnız assets/img daxilindəki şəkil faylının nisbi yolu qəbul olunur */
        $safePath = static function (string $p): string {
            $p = str_replace('\\', '/', trim($p));
            $p = preg_replace('~\.\.+/~', '', $p);          // ../ keçidləri
            $p = ltrim((string)$p, '/');
            if (!preg_match('~^[A-Za-z0-9._/-]{1,200}$~', $p)) return '';
            $ext = strtolower((string)pathinfo($p, PATHINFO_EXTENSION));
            return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true) ? $p : '';
        };

        foreach (($_POST['r'] ?? []) as $i => $row) {
            $id  = (int)($row['id'] ?? 0);

            if ($id && !empty($row['del'])) {
                $db->prepare('DELETE FROM clients WHERE id=?')->execute([$id]);
                $removed++;
                continue;
            }

            $raw = trim((string)($row['image'] ?? ''));
            $img = $safePath($raw);

            /* Yol yazılıb, amma yararsızdır (səhv uzantı, qadağan simvol) —
               sətri silmirik, köhnə şəkli saxlayıb xəbərdarlıq veririk. */
            if ($raw !== '' && $img === '') {
                $bad[] = $raw;
                $img = $id
                    ? (string)$db->query('SELECT image FROM clients WHERE id=' . $id)->fetchColumn()
                    : '';
            }

            if ($p = admin_upload('logo_' . (int)$i)) $img = $p;   // yeni fayl yüklənibsə
            $name = trim((string)($row['name'] ?? ''));
            $url  = trim((string)($row['url']  ?? ''));

            if ($img === '') {
                if ($id) { $db->prepare('DELETE FROM clients WHERE id=?')->execute([$id]); $removed++; }
                continue;
            }
            if ($id) {
                $db->prepare('UPDATE clients SET image=?, name=?, url=?, sort=? WHERE id=?')
                   ->execute([$img, $name, $url, (int)$i, $id]);
            } else {
                $db->prepare('INSERT INTO clients (image, name, url, sort) VALUES (?,?,?,?)')
                   ->execute([$img, $name, $url, (int)$i]);
                $added++;
            }
        }
        $msg = 'Сохранено.';
        if ($added)   $msg .= " Добавлено: $added.";
        if ($removed) $msg .= " Удалено: $removed.";
        flash($msg);
        if ($bad) {
            flash('Путь не принят (нужен файл .jpg/.png/.webp/.gif/.svg внутри assets/img): '
                . implode(', ', array_map('strip_tags', array_slice($bad, 0, 3))), 'err');
        }
        redirect('index.php?section=clients');
    }

    if (isset($bulk[$section]) && $act === 'save') {
        $saveBulk($section);
        flash('Сохранено.');
        redirect('index.php?section=' . $section . '&lang=' . $L);
    }

    if ($section === 'blocks' && $act === 'save') {
        foreach (['stats', 'process', 'whyus'] as $t) {
            $cols = $bulk[$t]; $key = $bulkKey[$t];
            foreach (($_POST[$t] ?? []) as $i => $row) {
                $id  = (int)($row['id'] ?? 0);
                $val = trim((string)($row[$key] ?? ''));
                if ($id && $val === '') { $db->prepare("DELETE FROM `$t` WHERE id=?")->execute([$id]); continue; }
                if ($val === '') continue;
                $vals = [];
                foreach ($cols as $c) $vals[] = trim((string)($row[$c] ?? ''));
                $vals[] = (int)$i;
                if ($id) {
                    $set = implode(',', array_map(fn($c) => "`$c`=?", array_merge($cols, ['sort'])));
                    $db->prepare("UPDATE `$t` SET $set WHERE id=?")->execute([...$vals, $id]);
                } else {
                    $cl = '`' . implode('`,`', array_merge($cols, ['sort', 'lang'])) . '`';
                    $ph = implode(',', array_fill(0, count($cols) + 2, '?'));
                    $db->prepare("INSERT INTO `$t` ($cl) VALUES ($ph)")->execute([...$vals, $L]);
                }
            }
        }
        flash('Блоки сохранены.');
        redirect('index.php?section=blocks&lang=' . $L);
    }

    if ($section === 'texts' && $act === 'save') {
        foreach (($_POST['t'] ?? []) as $k => $v) kvl_set('texts', (string)$k, $L, (string)$v);
        flash('Тексты сохранены.'); redirect('index.php?section=texts&lang=' . $L);
    }

    if ($section === 'contacts' && $act === 'save') {
        foreach (['phone','phone_raw','email','city','address','address_full','hours_week','hours_sat','facebook','instagram','whatsapp','map'] as $k)
            kvl_set('contacts', $k, $L, (string)($_POST[$k] ?? ''));
        flash('Контакты сохранены.'); redirect('index.php?section=contacts&lang=' . $L);
    }

    if ($section === 'services') {
        if ($act === 'save') {
            $img = trim($_POST['img'] ?? '');
            if ($p = admin_upload('img_file')) $img = $p;
            $f = [
                'slug' => trim($_POST['slug'] ?? ''), 'no' => trim($_POST['no'] ?? ''),
                'title' => $_POST['title'] ?? '', 'short' => $_POST['short'] ?? '',
                'img' => $img, 'lead' => $_POST['lead'] ?? '', 'body' => $_POST['body'] ?? '',
                'meta_title' => $_POST['meta_title'] ?? '', 'meta_desc' => $_POST['meta_desc'] ?? '',
                'sort' => (int)($_POST['sort'] ?? 0), 'visible' => isset($_POST['visible']) ? 1 : 0,
                'lang' => $L,
            ];
            if ($f['slug'] === '') { flash('Slug обязателен.', 'err'); redirect('index.php?section=services&lang=' . $L); }
            if (!empty($_POST['id'])) {
                $set = implode(',', array_map(fn($k) => "`$k`=?", array_keys($f)));
                $db->prepare("UPDATE services SET $set WHERE id=?")->execute([...array_values($f), (int)$_POST['id']]);
            } else {
                $cl = '`' . implode('`,`', array_keys($f)) . '`';
                $ph = implode(',', array_fill(0, count($f), '?'));
                $db->prepare("INSERT INTO services ($cl) VALUES ($ph)")->execute(array_values($f));
            }
            flash('Услуга сохранена.'); redirect('index.php?section=services&lang=' . $L);
        } elseif ($act === 'del') {
            $db->prepare('DELETE FROM services WHERE id=?')->execute([(int)$_POST['id']]);
            flash('Услуга удалена.'); redirect('index.php?section=services&lang=' . $L);
        }
    }

    if ($section === 'news') {
        if ($act === 'save') {
            $f = [
                'slug' => trim($_POST['slug'] ?? ''), 'title' => $_POST['title'] ?? '',
                'pub_date' => $_POST['pub_date'] ?? '', 'video' => trim($_POST['video'] ?? ''),
                'question' => $_POST['question'] ?? '', 'excerpt' => $_POST['excerpt'] ?? '',
                'body' => $_POST['body'] ?? '',
                'sort' => (int)($_POST['sort'] ?? 0), 'visible' => isset($_POST['visible']) ? 1 : 0,
                'is_own' => isset($_POST['is_own']) ? 1 : 0,
                'image' => news_cover_path($_POST['image'] ?? ''),
                'lang' => $L,
            ];
            if ($p = admin_upload('image_file')) $f['image'] = $p;
            if ($f['slug'] === '') { flash('Slug обязателен.', 'err'); redirect('index.php?section=news&lang=' . $L); }
            // tam YouTube linki yapışdırılıbsa — ID-ni çıxarırıq
            if (preg_match('~(?:youtu\.be/|v=|embed/)([A-Za-z0-9_-]{6,})~', $f['video'], $m)) $f['video'] = $m[1];
            if (!empty($_POST['id'])) {
                $set = implode(',', array_map(fn($k) => "`$k`=?", array_keys($f)));
                $db->prepare("UPDATE news SET $set WHERE id=?")->execute([...array_values($f), (int)$_POST['id']]);
            } else {
                $cl = '`' . implode('`,`', array_keys($f)) . '`';
                $ph = implode(',', array_fill(0, count($f), '?'));
                $db->prepare("INSERT INTO news ($cl) VALUES ($ph)")->execute(array_values($f));
            }
            flash('Новость сохранена.'); redirect('index.php?section=news&lang=' . $L);
        } elseif ($act === 'del') {
            $db->prepare('DELETE FROM news WHERE id=?')->execute([(int)$_POST['id']]);
            flash('Новость удалена.'); redirect('index.php?section=news&lang=' . $L);
        }
    }

    if ($section === 'pages' && $act === 'save') {
        $slug = (string)($_POST['slug'] ?? '');
        $st = $db->prepare('SELECT 1 FROM seo_pages WHERE slug=? AND lang=?'); $st->execute([$slug, $L]);
        if ($st->fetch()) {
            $db->prepare('UPDATE seo_pages SET title=?,descr=?,robots=?,canonical=?,hero_title=?,hero_text=?,body=? WHERE slug=? AND lang=?')
               ->execute([$_POST['title'] ?? '', $_POST['descr'] ?? '', $_POST['robots'] ?? 'index,follow', $_POST['canonical'] ?? '',
                          $_POST['hero_title'] ?? '', $_POST['hero_text'] ?? '', $_POST['body'] ?? '', $slug, $L]);
        } else {
            $db->prepare('INSERT INTO seo_pages (lang,slug,title,descr,robots,canonical,hero_title,hero_text,body) VALUES (?,?,?,?,?,?,?,?,?)')
               ->execute([$L, $slug, $_POST['title'] ?? '', $_POST['descr'] ?? '', $_POST['robots'] ?? 'index,follow', $_POST['canonical'] ?? '',
                          $_POST['hero_title'] ?? '', $_POST['hero_text'] ?? '', $_POST['body'] ?? '']);
        }
        flash('Страница сохранена.'); redirect('index.php?section=pages&lang=' . $L);
    }

    if ($section === 'seo' && $act === 'save') {
        foreach (($_POST['title'] ?? []) as $slug => $title) {
            $db->prepare('UPDATE seo_pages SET title=?, descr=? WHERE slug=? AND lang=?')
               ->execute([$title, $_POST['descr'][$slug] ?? '', $slug, $L]);
        }
        flash('SEO сохранён.'); redirect('index.php?section=seo&lang=' . $L);
    }

    if ($section === 'seo' && $act === 'save_ga') {
        $ga = trim((string)($_POST['ga_id'] ?? ''));
        if ($ga !== '' && !preg_match('/^(G|GT|UA|AW)-[A-Z0-9\-]{4,20}$/i', $ga)) {
            flash('Неверный формат номера. Пример: G-XXXXXXXXXX', 'err');
        } else {
            kv_set('settings', 'ga_id', $ga);
            flash($ga === '' ? 'Аналитика отключена.' : 'Номер аналитики сохранён.');
        }
        redirect('index.php?section=seo&lang=' . $L);
    }

    if ($section === 'submissions') {
        if ($act === 'read')     { $db->prepare('UPDATE submissions SET is_read=1 WHERE id=?')->execute([(int)$_POST['id']]); }
        elseif ($act === 'del')  { $db->prepare('DELETE FROM submissions WHERE id=?')->execute([(int)$_POST['id']]); flash('Заявка удалена.'); }
        elseif ($act === 'allread') { $db->exec('UPDATE submissions SET is_read=1'); flash('Все отмечены прочитанными.'); }
        redirect('index.php?section=submissions');
    }

    if ($section === 'images' && $act === 'upload') {
        if ($p = admin_upload('file')) flash('Загружено: ' . $p);
        else flash('Не удалось загрузить (jpg/png/webp/gif/svg/mp4/webm).', 'err');
        redirect('index.php?section=images');
    }

    if ($section === 'security' && $act === 'save') {
        kv_set('settings', 'turnstile_site', trim($_POST['turnstile_site'] ?? ''));
        if (($_POST['turnstile_secret'] ?? '') !== '') kv_set('settings', 'turnstile_secret', trim($_POST['turnstile_secret']));
        flash('Настройки безопасности сохранены.'); redirect('index.php?section=security');
    }

    if ($section === 'smtp') {
        if ($act === 'save') {
            foreach (['smtp_host','smtp_port','smtp_user','smtp_from','smtp_from_name','smtp_secure'] as $k)
                kv_set('settings', $k, (string)($_POST[$k] ?? ''));
            if (($_POST['smtp_pass'] ?? '') !== '') kv_set('settings', 'smtp_pass', (string)$_POST['smtp_pass']);
            kv_set('texts', 'mail_to', trim($_POST['mail_to'] ?? ''));
            flash('Настройки почты сохранены.'); redirect('index.php?section=smtp');
        } elseif ($act === 'test') {
            $err = ''; $to = trim($_POST['test_to'] ?? '');
            if (smtp_send($to, 'Тест SMTP — caucasuscl.com', "Это тестовое письмо из админ-панели.\nЕсли вы его получили — SMTP настроен верно.", $err))
                flash('Тестовое письмо отправлено на ' . $to);
            else flash('Ошибка отправки: ' . $err, 'err');
            redirect('index.php?section=smtp');
        }
    }

    if ($section === 'profile') {
        $a = current_admin();
        if ($act === 'save') {
            $db->prepare('UPDATE admins SET name=?, email=? WHERE id=?')->execute([$_POST['name'] ?? '', trim($_POST['email'] ?? ''), $a['id']]);
            $_SESSION['admin']['name']  = $_POST['name'] ?? '';
            $_SESSION['admin']['email'] = trim($_POST['email'] ?? '');
            flash('Профиль сохранён.');
        } elseif ($act === 'passwd') {
            $st = $db->prepare('SELECT pass_hash FROM admins WHERE id=?'); $st->execute([$a['id']]); $row = $st->fetch();
            if (!password_verify($_POST['old'] ?? '', $row['pass_hash'] ?? ''))  flash('Текущий пароль неверный.', 'err');
            elseif (strlen($_POST['new'] ?? '') < 6)                            flash('Новый пароль слишком короткий (мин. 6).', 'err');
            elseif (($_POST['new'] ?? '') !== ($_POST['new2'] ?? ''))           flash('Пароли не совпадают.', 'err');
            else { $db->prepare('UPDATE admins SET pass_hash=? WHERE id=?')->execute([password_hash($_POST['new'], PASSWORD_DEFAULT), $a['id']]); flash('Пароль изменён.'); }
        }
        redirect('index.php?section=profile');
    }

    if ($section === 'users') {
        $me = (int)current_admin()['id'];
        if ($act === 'add') {
            $em = trim($_POST['email'] ?? '');
            if (!filter_var($em, FILTER_VALIDATE_EMAIL))       flash('Некорректный e-mail.', 'err');
            elseif (strlen($_POST['password'] ?? '') < 8)      flash('Пароль минимум 8 символов.', 'err');
            else {
                $chk = $db->prepare('SELECT id FROM admins WHERE email=?'); $chk->execute([$em]);
                if ($chk->fetch()) flash('Пользователь с таким e-mail уже есть.', 'err');
                else {
                    $db->prepare('INSERT INTO admins (name,email,pass_hash,role,active) VALUES (?,?,?,?,1)')
                       ->execute([$_POST['name'] ?? '', $em, password_hash($_POST['password'], PASSWORD_DEFAULT), ($_POST['role'] ?? 'editor') === 'admin' ? 'admin' : 'editor']);
                    flash('Пользователь добавлен.');
                }
            }
        } elseif ($act === 'save') {
            foreach (($_POST['u'] ?? []) as $id => $row) {
                $id = (int)$id;
                $role   = ($row['role'] ?? 'editor') === 'admin' ? 'admin' : 'editor';
                $active = isset($row['active']) ? 1 : 0;
                if ($id === $me) { $active = 1; $role = 'admin'; }
                $db->prepare('UPDATE admins SET name=?, role=?, active=? WHERE id=?')->execute([$row['name'] ?? '', $role, $active, $id]);
                if (($row['password'] ?? '') !== '' && strlen($row['password']) >= 8)
                    $db->prepare('UPDATE admins SET pass_hash=? WHERE id=?')->execute([password_hash($row['password'], PASSWORD_DEFAULT), $id]);
            }
            flash('Изменения сохранены.');
        } elseif (!empty($_POST['delete_id'])) {
            $id = (int)$_POST['delete_id'];
            if ($id !== $me) { $db->prepare('DELETE FROM admins WHERE id=?')->execute([$id]); flash('Пользователь удалён.'); }
            else flash('Нельзя удалить самого себя.', 'err');
        }
        redirect('index.php?section=users');
    }
}

/* ================== RENDER ================== */
layout_top($section, SECTIONS[$section][0]);

/* Dilə bağlı cədvəllərdə yalnız cari dilin sətirləri sayılır,
   yoxsa 7 xidmət 3 dil × 7 = 21 kimi görünür. */
$count = function (string $t) use ($db, $L): int {
    $perLang = ['services', 'news', 'links', 'stats', 'process', 'whyus'];
    try {
        if (in_array($t, $perLang, true)) {
            $st = $db->prepare("SELECT COUNT(*) c FROM `$t` WHERE lang = ?");
            $st->execute([$L]);
            return (int)$st->fetch()['c'];
        }
        return (int)$db->query("SELECT COUNT(*) c FROM `$t`")->fetch()['c'];
    } catch (Throwable $e) { return 0; }
};

/* --- ümumi: sadə sətir redaktoru --- */
function bulk_table(PDO $db, string $table, string $arrName, array $fields, int $extra = 2): void {
    // clients dilsizdir (loqolar bütün dillərdə eynidir), qalanlar dilə görə süzülür
    if ($table === 'clients') {
        $rows = $db->query("SELECT * FROM `clients` ORDER BY sort, id")->fetchAll();
    } else {
        $st = $db->prepare("SELECT * FROM `$table` WHERE lang = ? ORDER BY sort, id");
        $st->execute([cur_lang()]);
        $rows = $st->fetchAll();
    }
    for ($i = 0; $i < $extra; $i++) $rows[] = [];
    echo '<div class="tablewrap"><table><tr>';
    foreach ($fields as $lbl) echo '<th>' . e($lbl) . '</th>';
    echo '</tr>';
    foreach ($rows as $i => $r) {
        echo '<tr><input type="hidden" name="' . $arrName . '[' . $i . '][id]" value="' . e($r['id'] ?? '') . '">';
        foreach ($fields as $name => $lbl) {
            $v = $r[$name] ?? '';
            $long = in_array($name, ['descr', 'url'], true);
            if ($long) echo '<td><textarea name="' . $arrName . '[' . $i . '][' . $name . ']" style="min-height:56px">' . e($v) . '</textarea></td>';
            else       echo '<td><input type="text" name="' . $arrName . '[' . $i . '][' . $name . ']" value="' . e($v) . '"></td>';
        }
        echo '</tr>';
    }
    echo '</table></div>';
}

/* ---------------- OVERVIEW ---------------- */
if ($section === 'overview') {
    $unread = unread_count();
    echo '<div class="cards">';
    foreach ([['services', 'услуг'], ['news', 'новостей'], ['clients', 'клиентов'], ['submissions', 'заявок']] as [$t, $l])
        echo '<div class="card"><div class="n">' . $count($t) . '</div><div class="l">' . $l . '</div></div>';
    echo '</div>';

    if ($unread) echo '<div class="panel" style="border-color:#f5c518;background:#fffdf2"><h3>🔔 Новых заявок: ' . $unread . '</h3>'
        . '<a class="btn" href="index.php?section=submissions">Посмотреть</a></div>';

    echo '<div class="panel"><h3>Быстрые действия</h3><p class="muted">Чаще всего меняют тексты, услуги, контакты и смотрят заявки.</p><div style="margin-top:12px">';
    foreach ([['texts', 'Тексты сайта'], ['services', 'Услуги'], ['news', 'Новости'], ['contacts', 'Контакты'], ['submissions', 'Заявки']] as [$s, $l])
        echo '<a class="btn ghost" style="margin:0 8px 8px 0" href="index.php?section=' . $s . '">' . $l . '</a>';
    echo '</div></div>';

    $drv = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    echo '<div class="panel"><h3>Система</h3><table>';
    echo '<tr><td>База данных</td><td><b>' . e($drv === 'sqlite' ? 'SQLite (файл)' : 'MySQL') . '</b></td></tr>';
    echo '<tr><td>Капча Turnstile</td><td>' . (kv_get('settings', 'turnstile_secret') !== '' ? '<span class="pill">включена</span>' : '<span class="muted">выключена</span>') . '</td></tr>';
    echo '<tr><td>Отправка почты</td><td>' . (kv_get('settings', 'smtp_host') !== '' ? '<b>SMTP</b>' : 'функция mail()') . '</td></tr>';
    echo '<tr><td>PHP</td><td>' . e(PHP_VERSION) . '</td></tr>';
    echo '</table></div>';
}

/* ---------------- TEXTS ---------------- */
elseif ($section === 'texts') {
    $t = fn($k) => e(kvl_get('texts', $k, $L));
    echo '<form method="post" class="panel">' . csrf_field() . '<input type="hidden" name="action" value="save">';
    echo '<h3>Общее</h3>';
    echo '<div class="row"><div><label>Короткое имя</label><input type="text" name="t[site_short]" value="' . $t('site_short') . '"></div>';
    echo '<div><label>Полное имя компании</label><input type="text" name="t[site_name]" value="' . $t('site_name') . '"></div></div>';
    echo '<label>Слоган</label><input type="text" name="t[site_slogan]" value="' . $t('site_slogan') . '">';

    echo '<h4>Первый экран (главная)</h4>';
    echo '<label>Плашка над заголовком</label><input type="text" name="t[hero_badge]" value="' . $t('hero_badge') . '">';
    echo '<div class="row"><div><label>Заголовок</label><textarea name="t[hero_title]">' . $t('hero_title') . '</textarea></div>';
    echo '<div><label>Выделенная жёлтым часть</label><input type="text" name="t[hero_accent]" value="' . $t('hero_accent') . '"><p class="muted">Дописывается к заголовку и подсвечивается.</p></div></div>';
    echo '<label>Текст под заголовком</label><textarea name="t[hero_text]">' . $t('hero_text') . '</textarea>';

    echo '<h4>Заголовки секций главной</h4>';
    echo '<div class="row"><div><label>Услуги — заголовок</label><input type="text" name="t[svc_title]" value="' . $t('svc_title') . '"></div>';
    echo '<div><label>Услуги — подпись</label><textarea name="t[svc_text]">' . $t('svc_text') . '</textarea></div></div>';
    echo '<div class="row"><div><label>Процесс — заголовок</label><input type="text" name="t[process_title]" value="' . $t('process_title') . '"></div>';
    echo '<div><label>Процесс — подпись</label><textarea name="t[process_text]">' . $t('process_text') . '</textarea></div></div>';
    echo '<div class="row"><div><label>Новости — заголовок</label><input type="text" name="t[news_title]" value="' . $t('news_title') . '"></div>';
    echo '<div><label>Новости — подпись</label><textarea name="t[news_text]">' . $t('news_text') . '</textarea></div></div>';
    echo '<label>Клиенты — заголовок</label><input type="text" name="t[clients_title]" value="' . $t('clients_title') . '">';

    echo '<h4>Блок «О нас»</h4>';
    echo '<label>Заголовок</label><input type="text" name="t[about_title]" value="' . $t('about_title') . '">';
    echo '<label>Абзац 1</label><textarea name="t[about_p1]" style="min-height:110px">' . $t('about_p1') . '</textarea>';
    echo '<label>Абзац 2</label><textarea name="t[about_p2]" style="min-height:110px">' . $t('about_p2') . '</textarea>';
    echo '<label>Пункты с галочками (каждый с новой строки)</label><textarea name="t[about_ticks]" style="min-height:110px">' . $t('about_ticks') . '</textarea>';

    echo '<div style="margin-top:18px"><button class="btn">Сохранить тексты</button></div></form>';
}

/* ---------------- PAGES ---------------- */
elseif ($section === 'pages') {
    $labels = ['/' => 'Главная', 'haqqimizda' => 'О нас', 'xidmetler' => 'Услуги (список)', 'xeberler' => 'Новости (список)', 'elaqe' => 'Контакты', 'linkler' => 'Полезные ссылки'];
    if (isset($_GET['edit'])) {
        $slug = (string)$_GET['edit'];
        $st = $db->prepare('SELECT * FROM seo_pages WHERE slug=? AND lang=?'); $st->execute([$slug, $L]);
        $pg = $st->fetch() ?: ['slug' => $slug, 'title' => '', 'descr' => '', 'robots' => 'index,follow', 'canonical' => '', 'hero_title' => '', 'hero_text' => '', 'body' => ''];
        $url = $slug === '/' ? '/' : '/' . $slug;
        echo '<form method="post" class="panel">' . csrf_field() . '<input type="hidden" name="action" value="save"><input type="hidden" name="slug" value="' . e($slug) . '">';
        echo '<h3>' . e($labels[$slug] ?? $slug) . '</h3><p class="muted">Адрес: <b>' . e($url) . '</b></p>';

        echo '<h4>Содержание страницы</h4>';
        echo '<label>Заголовок (H1)</label><input type="text" name="hero_title" value="' . e($pg['hero_title'] ?? '') . '">';
        echo '<label>Подзаголовок под H1</label><textarea name="hero_text">' . e($pg['hero_text'] ?? '') . '</textarea>';
        if (in_array($slug, ['haqqimizda', 'elaqe', 'linkler'], true)) {
            echo '<label>Основной текст</label><textarea name="body" data-rich style="min-height:300px;font-family:ui-monospace,Consolas,monospace;font-size:13px">' . e($pg['body'] ?? '') . '</textarea>';
            echo '<div class="muted" style="margin-top:8px;background:#f4f7f8;padding:12px 14px;border-radius:9px;line-height:1.7">' . '<b>Разметка исходного текста</b> — нужна, только если вы переключились на него. В визуальном редакторе всё делают кнопки.<br>' . '<code>## Заголовок</code> &middot; <code>### Подзаголовок</code> &middot; обычная строка — абзац<br>' . '<code>- пункт</code> — маркированный список &middot; <code>+ пункт</code> — нумерованный<br>' . '<code>**жирный**</code> &middot; <code>*курсив*</code> &middot; <code>[текст](адрес)</code> — ссылка &middot; <code>* Название | Описание</code> — карточка' . '</div>';
        } else {
            echo '<input type="hidden" name="body" value="' . e($pg['body'] ?? '') . '">';
            echo '<p class="muted">Остальной контент этой страницы формируется автоматически из разделов «Услуги» / «Новости».</p>';
        }

        echo '<h4>SEO</h4>';
        echo '<label>SEO Title (заголовок вкладки и в поиске)</label><input type="text" name="title" value="' . e($pg['title']) . '">';
        echo '<label>Meta Description</label><textarea name="descr">' . e($pg['descr']) . '</textarea>';
        echo '<div class="row"><div><label>Robots</label><input type="text" name="robots" value="' . e($pg['robots'] ?? 'index,follow') . '"></div>';
        echo '<div><label>Canonical</label><input type="text" name="canonical" value="' . e($pg['canonical'] ?? '') . '"></div></div>';
        echo '<div style="margin-top:18px"><button class="btn">Сохранить</button> <a class="btn ghost" href="index.php?section=pages&lang=' . $L . '">Назад</a> <a class="btn ghost" href="..' . e($url) . '" target="_blank">Открыть на сайте</a></div></form>';
    } else {
        $st = $db->prepare('SELECT * FROM seo_pages WHERE lang = ? ORDER BY slug'); $st->execute([$L]); $rows = $st->fetchAll();
        echo '<div class="panel"><p class="muted">Содержимое страниц редактируется в разделах «Тексты», «Услуги», «Новости». Здесь — адреса и SEO.</p>';
        echo '<div class="tablewrap"><table><tr><th>Страница</th><th>Адрес</th><th>Текст</th><th>SEO</th><th></th></tr>';
        foreach ($rows as $r) {
            $url = $r['slug'] === '/' ? '/' : '/' . $r['slug'];
            $dot = trim((string)$r['title']) !== '' ? '#0f8a4f' : '#cbd5d9';
            echo '<tr><td><b>' . e($labels[$r['slug']] ?? $r['slug']) . '</b></td>';
            echo '<td><span class="muted" style="background:#f0f4f5;padding:2px 8px;border-radius:6px">' . e($url) . '</span></td>';
            $hasBody = trim((string)($r['hero_title'] ?? '')) !== '' || trim((string)($r['body'] ?? '')) !== '';
            echo '<td><span class="dot" style="background:' . ($hasBody ? '#0f8a4f' : '#cbd5d9') . '"></span></td>';
            echo '<td><span class="dot" style="background:' . $dot . '"></span></td>';
            echo '<td class="right" style="white-space:nowrap"><a class="btn sm ghost" href="index.php?section=pages&edit=' . rawurlencode($r['slug']) . '&lang=' . $L . '">Редактировать</a> ';
            echo '<a class="btn sm ghost" href="..' . e($url) . '" target="_blank">Открыть</a></td></tr>';
        }
        echo '</table></div></div>';
    }
}

/* ---------------- SERVICES ---------------- */
elseif ($section === 'services') {
    $mode = isset($_GET['edit']) ? 'edit' : (isset($_GET['new']) ? 'new' : 'list');
    if ($mode === 'list') {
        $st = $db->prepare('SELECT * FROM services WHERE lang = ? ORDER BY sort, id'); $st->execute([$L]); $rows = $st->fetchAll();
        echo '<div class="panel"><div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;gap:12px;flex-wrap:wrap">';
        echo '<div class="muted">Всего: ' . count($rows) . '</div><a class="btn" href="index.php?section=services&new=1&lang=' . $L . '">+ Добавить услугу</a></div>';
        echo '<div class="tablewrap"><table><tr><th>№</th><th>Фото</th><th>Название</th><th>Адрес</th><th>SEO</th><th></th></tr>';
        foreach ($rows as $r) {
            $dot = trim((string)$r['meta_title']) !== '' ? '#0f8a4f' : '#cbd5d9';
            echo '<tr><td><b class="num">' . e($r['no']) . '</b></td>';
            echo '<td>' . ($r['img'] ? '<img src="../assets/img/' . e($r['img']) . '" style="width:72px;height:46px;object-fit:cover;border-radius:6px">' : '—') . '</td>';
            echo '<td><b>' . e($r['title']) . '</b>' . ((int)$r['visible'] ? '' : ' <span class="muted">(скрыта)</span>') . '</td>';
            echo '<td class="muted">/xidmetler/' . e($r['slug']) . '</td>';
            echo '<td><span class="dot" style="background:' . $dot . '"></span></td>';
            echo '<td class="right" style="white-space:nowrap"><a class="btn sm ghost" href="index.php?section=services&edit=' . $r['id'] . '&lang=' . $L . '">Изменить</a> ';
            echo '<a class="btn sm ghost" href="../' . ($L === 'az' ? '' : $L . '/') . 'xidmetler/' . e($r['slug']) . '" target="_blank">Открыть</a> ';
            echo '<form method="post" style="display:inline" data-confirm="Удалить услугу?">' . csrf_field() . '<input type="hidden" name="action" value="del"><input type="hidden" name="id" value="' . $r['id'] . '"><button class="btn sm red">✕</button></form></td></tr>';
        }
        echo '</table></div></div>';
    } else {
        $s = ['id' => '', 'slug' => '', 'no' => '', 'title' => '', 'short' => '', 'img' => '', 'lead' => '', 'body' => '', 'meta_title' => '', 'meta_desc' => '', 'sort' => 0, 'visible' => 1];
        if ($mode === 'edit') { $st = $db->prepare('SELECT * FROM services WHERE id=?'); $st->execute([(int)$_GET['edit']]); $s = array_merge($s, $st->fetch() ?: []); }
        echo '<form method="post" enctype="multipart/form-data" class="panel">' . csrf_field() . '<input type="hidden" name="action" value="save"><input type="hidden" name="id" value="' . e($s['id']) . '">';
        echo '<h3>' . ($mode === 'edit' ? 'Изменить услугу' : 'Новая услуга') . '</h3>';
        echo '<div class="row3"><div><label>Номер (01…)</label><input type="text" name="no" value="' . e($s['no']) . '"></div>';
        echo '<div><label>Slug (адрес)</label><input type="text" name="slug" value="' . e($s['slug']) . '"></div>';
        echo '<div><label>Порядок</label><input type="number" name="sort" value="' . e($s['sort']) . '"></div></div>';
        echo '<label>Название</label><input type="text" name="title" value="' . e($s['title']) . '">';
        echo '<label>Короткое описание (для карточки)</label><textarea name="short">' . e($s['short']) . '</textarea>';
        echo '<label>Картинка (путь внутри assets/img)</label><input type="text" name="img" value="' . e($s['img']) . '">';
        if ($s['img']) echo '<div style="margin-top:8px"><img src="../assets/img/' . e($s['img']) . '" style="max-width:230px;border-radius:8px"></div>';
        echo '<label>Загрузить новую картинку</label><input type="file" name="img_file" accept="image/*">';
        echo '<label>Вводный абзац (lead)</label><textarea name="lead" style="min-height:110px">' . e($s['lead']) . '</textarea>';
        echo '<label>Содержание страницы</label><textarea name="body" data-rich style="min-height:280px;font-family:ui-monospace,Consolas,monospace;font-size:13px">' . e($s['body']) . '</textarea>';
        echo '<div class="muted" style="margin-top:8px;background:#f4f7f8;padding:12px 14px;border-radius:9px;line-height:1.7">'
           . '<b>Разметка исходного текста</b> — нужна, только если вы переключились на него. В визуальном редакторе всё делают кнопки.<br>' . '<code>## Заголовок</code> &middot; <code>### Подзаголовок</code> &middot; обычная строка — абзац<br>' . '<code>- пункт</code> — маркированный список &middot; <code>+ пункт</code> — нумерованный<br>' . '<code>**жирный**</code> &middot; <code>*курсив*</code> &middot; <code>[текст](адрес)</code> — ссылка &middot; <code>* Название | Описание</code> — карточка' . '</div>';
        echo '<h4>SEO этой услуги</h4>';
        echo '<label>SEO Title</label><input type="text" name="meta_title" value="' . e($s['meta_title']) . '">';
        echo '<label>Meta Description</label><textarea name="meta_desc">' . e($s['meta_desc']) . '</textarea>';
        echo '<label style="font-weight:400;margin-top:16px"><input type="checkbox" name="visible" ' . (!empty($s['visible']) ? 'checked' : '') . '> Показывать на сайте</label>';
        echo '<div style="margin-top:18px"><button class="btn">Сохранить</button> <a class="btn ghost" href="index.php?section=services&lang=' . $L . '">Назад к списку</a></div></form>';
    }
}

/* ---------------- NEWS ---------------- */
elseif ($section === 'news') {
    $mode = isset($_GET['edit']) ? 'edit' : (isset($_GET['new']) ? 'new' : 'list');
    if ($mode === 'list') {
        $st = $db->prepare('SELECT * FROM news WHERE lang = ? ORDER BY sort, id'); $st->execute([$L]); $rows = $st->fetchAll();
        echo '<div class="panel"><div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;gap:12px;flex-wrap:wrap">';
        echo '<div class="muted">Всего: ' . count($rows) . '</div><a class="btn" href="index.php?section=news&new=1&lang=' . $L . '">+ Добавить новость</a></div>';
        echo '<div class="tablewrap"><table><tr><th>Обложка</th><th>Дата</th><th>Заголовок</th><th>Видео</th><th></th></tr>';
        $imgRoot = dirname(__DIR__) . '/assets/img/';
        foreach ($rows as $r) {
            echo '<tr>';
            /* üz qabığı — siyahıda dərhal görünsün */
            $cov = trim((string)($r['image'] ?? ''));
            echo '<td style="width:96px">';
            if ($cov !== '' && is_file($imgRoot . $cov)) {
                echo '<img src="../assets/img/' . e($cov) . '" alt="" style="width:84px;height:52px;object-fit:cover;border-radius:6px;border:1px solid #eef2f4">';
            } else {
                echo '<div style="width:84px;height:52px;display:grid;place-items:center;background:#f4f7f8;border:1px dashed #cbd6dd;border-radius:6px;font-size:10px;color:#9fb0bc">общая</div>';
            }
            echo '</td>';
            echo '<td class="muted" style="white-space:nowrap">' . e($r['pub_date']) . '</td>';
            $pub = public_url('/xeberler/' . $r['slug'], $L);
            echo '<td><b>' . e($r['title']) . '</b><br><span class="muted" style="font-size:12px">' . e(parse_url($pub, PHP_URL_PATH)) . '</span></td>';
            echo '<td>' . ($r['video'] ? '<span class="pill">' . e($r['video']) . '</span>' : '<span class="muted">—</span>') . '</td>';
            echo '<td class="right" style="white-space:nowrap"><a class="btn sm ghost" href="index.php?section=news&edit=' . $r['id'] . '&lang=' . $L . '">Изменить</a> ';
            echo '<a class="btn sm ghost" href="' . e($pub) . '" target="_blank" rel="noopener">Открыть</a> ';
            echo '<form method="post" style="display:inline" data-confirm="Удалить новость?">' . csrf_field() . '<input type="hidden" name="action" value="del"><input type="hidden" name="id" value="' . $r['id'] . '"><button class="btn sm red">✕</button></form></td></tr>';
        }
        echo '</table></div></div>';
    } else {
        $n = ['id' => '', 'slug' => '', 'title' => '', 'pub_date' => date('Y-m-d'), 'video' => '', 'question' => '', 'excerpt' => '', 'body' => '', 'sort' => 0, 'visible' => 1, 'is_own' => 0, 'image' => ''];
        if ($mode === 'edit') { $st = $db->prepare('SELECT * FROM news WHERE id=?'); $st->execute([(int)$_GET['edit']]); $n = array_merge($n, $st->fetch() ?: []); }
        echo '<form method="post" enctype="multipart/form-data" class="panel">' . csrf_field() . '<input type="hidden" name="action" value="save"><input type="hidden" name="id" value="' . e($n['id']) . '">';
        echo '<div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">';
        echo '<h3 style="margin:0">' . ($mode === 'edit' ? 'Изменить новость' : 'Новая новость') . '</h3>';
        if ($mode === 'edit' && trim((string)$n['slug']) !== '') {
            $__u = public_url('/xeberler/' . $n['slug'], $L);
            echo '<a class="btn sm ghost" href="' . e($__u) . '" target="_blank" rel="noopener">Открыть на сайте &#8599;</a>';
            if (empty($n['visible'])) echo '<span class="muted" style="font-size:12.5px">скрыта — страница отдаёт 404</span>';
        }
        echo '</div>';
        echo '<label>Заголовок</label><input type="text" name="title" value="' . e($n['title']) . '">';
        echo '<div class="row3"><div><label>Slug (адрес)</label><input type="text" name="slug" value="' . e($n['slug']) . '"></div>';
        echo '<div><label>Дата</label><input type="date" name="pub_date" value="' . e($n['pub_date']) . '"></div>';
        echo '<div><label>Порядок</label><input type="number" name="sort" value="' . e($n['sort']) . '"></div></div>';
        echo '<label style="margin-top:18px">Обложка новости</label>';
        $__img = (string)($n['image'] ?? '');
        $__abs = dirname(__DIR__) . '/assets/img/' . $__img;
        echo '<div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap">';
        echo '<div style="flex:none;width:200px">';
        if ($__img !== '' && is_file($__abs)) {
            echo '<img src="../assets/img/' . e($__img) . '" alt="" style="width:200px;height:116px;object-fit:cover;border:1px solid #eef2f4;border-radius:8px;background:#f4f7f8">';
        } else {
            echo '<div style="width:200px;height:116px;display:grid;place-items:center;background:#f4f7f8;border:1px dashed #cbd6dd;border-radius:8px;font-size:11.5px;color:#8aa0ae;text-align:center;padding:8px">'
               . ($__img === '' ? 'нет обложки —<br>возьмётся общая' : 'файл не найден') . '</div>';
        }
        echo '<input type="file" name="image_file" accept="image/*" style="width:200px;font-size:10.5px;margin-top:6px">';
        echo '</div>';
        echo '<div style="flex:1 1 300px;min-width:260px">';
        echo '<input type="text" name="image" value="' . e($__img) . '" placeholder="например: admin/oblozhka.jpg">';
        if ($__img !== '' && !is_file($__abs)) echo '<p class="muted" style="color:#b91c1c;margin:6px 0 0">Файла нет в <code>assets/img/</code>.</p>';
        echo '<p class="muted" style="margin:8px 0 0">Выберите файл — загрузится сам. Если оставить пусто, возьмётся обложка '
           . 'этой же новости с другого языка, а если и её нет — общая картинка новостей. Лучший размер — 1200&times;700.</p>';
        echo '</div></div>';
        echo '<label>YouTube — ID или полная ссылка</label><input type="text" name="video" value="' . e($n['video']) . '" placeholder="1V4WXnLFI9g">';
        if ($n['video']) echo '<div style="margin-top:10px"><iframe width="320" height="180" style="border:0;border-radius:10px" src="https://www.youtube.com/embed/' . e($n['video']) . '" allowfullscreen></iframe></div>';
        echo '<label>Вопрос (блок «Sual»)</label><textarea name="question" style="min-height:110px">' . e($n['question']) . '</textarea>';
        echo '<label>Краткое описание (для карточки и SEO)</label><textarea name="excerpt">' . e($n['excerpt']) . '</textarea>';
        echo '<label>Полный текст новости</label><textarea name="body" data-rich style="min-height:280px;font-family:ui-monospace,Consolas,monospace;font-size:13px">' . e($n['body'] ?? '') . '</textarea>';
        echo '<div class="muted" style="margin-top:8px;background:#f4f7f8;padding:12px 14px;border-radius:9px;line-height:1.7">' . '<b>Разметка исходного текста</b> — нужна, только если вы переключились на него. В визуальном редакторе всё делают кнопки.<br>' . '<code>## Заголовок</code> &middot; <code>### Подзаголовок</code> &middot; обычная строка — абзац<br>' . '<code>- пункт</code> — маркированный список &middot; <code>+ пункт</code> — нумерованный<br>' . '<code>**жирный**</code> &middot; <code>*курсив*</code> &middot; <code>[текст](адрес)</code> — ссылка &middot; <code>* Название | Описание</code> — карточка' . '</div>';
        echo '<label style="font-weight:400;margin-top:16px"><input type="checkbox" name="visible" ' . (!empty($n['visible']) ? 'checked' : '') . '> Показывать на сайте</label>';
        echo '<label style="font-weight:400;margin-top:10px"><input type="checkbox" name="is_own" ' . (!empty($n['is_own']) ? 'checked' : '') . '> Собственная статья компании</label>';
        echo '<p class="muted" style="margin-top:6px">Снято — материал подписывается как вопрос-ответ Государственного таможенного комитета. '
           . 'Отмечено — подписывается именем компании. Указывать источник неверно нельзя.</p>';
        echo '<div style="margin-top:18px"><button class="btn">Сохранить</button> <a class="btn ghost" href="index.php?section=news&lang=' . $L . '">Назад к списку</a></div></form>';
    }
}

/* ---------------- BLOCKS (цифры / процесс / преимущества) ---------------- */
elseif ($section === 'blocks') {
    echo '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="save">';

    echo '<div class="panel"><h3>Цифры (счётчики на главной)</h3><p class="muted">Чтобы удалить строку — очистите «Подпись» и сохраните.</p>';
    bulk_table($db, 'stats', 'stats', ['num' => 'Число', 'suffix' => 'Знак (+)', 'label' => 'Подпись']);
    echo '</div>';

    echo '<div class="panel"><h3>Как мы работаем (4 шага)</h3><p class="muted">Чтобы удалить шаг — очистите «Заголовок».</p>';
    bulk_table($db, 'process', 'process', ['num' => '№', 'title' => 'Заголовок', 'descr' => 'Описание']);
    echo '</div>';

    echo '<div class="panel"><h3>«Почему мы» (карточки на страницах услуг)</h3><p class="muted">Чтобы удалить карточку — очистите «Заголовок».</p>';
    bulk_table($db, 'whyus', 'whyus', ['title' => 'Заголовок', 'descr' => 'Описание']);
    echo '</div>';

    echo '<button class="btn">Сохранить всё</button></form>';
}

/* ---------------- CLIENTS ---------------- */
elseif ($section === 'clients') {
    $rows = $db->query('SELECT * FROM clients ORDER BY sort, id')->fetchAll();
    $imgRoot = dirname(__DIR__) . '/assets/img/';

    echo '<form method="post" enctype="multipart/form-data" class="panel">' . csrf_field()
       . '<input type="hidden" name="action" value="save_clients">';
    echo '<h3>Логотипы клиентов <span class="muted" style="font-weight:400;font-size:13px">— '
       . count($rows) . ' шт.</span></h3>';
    echo '<p class="muted">Выберите файл — логотип загрузится сам. Порядок строк = порядок на сайте. '
       . 'Чтобы убрать логотип, отметьте «Удалить» и сохраните.</p>';

    $row = static function (int $i, array $r, string $imgRoot): void {
        $img    = (string)($r['image'] ?? '');
        $exists = $img !== '' && is_file($imgRoot . $img);
        $isNew  = empty($r['id']);

        echo '<div class="item" style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap">';
        echo '<input type="hidden" name="r[' . $i . '][id]" value="' . e($r['id'] ?? '') . '">';

        /* --- şəkil --- */
        echo '<div style="flex:none;width:130px;text-align:center">';
        if ($img !== '' && $exists) {
            echo '<img src="../assets/img/' . e($img) . '" alt="" '
               . 'style="width:130px;height:74px;object-fit:contain;background:#f4f7f8;'
               . 'border:1px solid #eef2f4;border-radius:8px">';
        } else {
            echo '<div style="width:130px;height:74px;display:grid;place-items:center;'
               . 'background:#f4f7f8;border:1px dashed #cbd6dd;border-radius:8px;'
               . 'font-size:11px;color:#8aa0ae;padding:6px;text-align:center">'
               . ($img === '' ? 'нет файла' : 'файл не найден') . '</div>';
        }
        echo '<input type="file" name="logo_' . $i . '" accept="image/*" '
           . 'style="width:130px;font-size:10.5px;margin-top:6px">';
        echo '</div>';

        /* --- sahələr --- */
        echo '<div style="flex:1 1 320px;min-width:260px">';
        echo '<label>Путь к файлу</label><input type="text" name="r[' . $i . '][image]" value="' . e($img) . '"'
           . ' placeholder="например: admin/logo.png"' . ($exists || $img === '' ? '' : ' style="border-color:#dc2626"') . '>';
        if ($img !== '' && !$exists) {
            echo '<p class="muted" style="color:#b91c1c;margin:4px 0 0">Файла нет в <code>assets/img/</code> — '
               . 'логотип не покажется на сайте.</p>';
        }
        echo '<div class="row"><div><label>Название</label>'
           . '<input type="text" name="r[' . $i . '][name]" value="' . e($r['name'] ?? '') . '"></div>';
        echo '<div><label>Ссылка (необязательно)</label>'
           . '<input type="text" name="r[' . $i . '][url]" value="' . e($r['url'] ?? '') . '"></div></div>';
        if (!$isNew) {
            echo '<label style="display:inline-flex;align-items:center;gap:8px;margin-top:10px;cursor:pointer">'
               . '<input type="checkbox" name="r[' . $i . '][del]" value="1" style="width:auto;margin:0">'
               . '<span style="color:#b91c1c;font-weight:600">Удалить</span></label>';
        }
        echo '</div></div>';
    };

    $i = 0;
    foreach ($rows as $r) $row($i++, $r, $imgRoot);

    echo '<h3 style="margin-top:26px">Добавить логотип</h3>';
    $row($i++, [], $imgRoot);
    $row($i++, [], $imgRoot);

    echo '<div style="margin-top:16px"><button class="btn">Сохранить</button></div></form>';

    echo '<div class="panel"><h3>Как это выглядит на сайте</h3>';
    if ($rows) {
        echo '<div style="display:flex;gap:26px;align-items:center;flex-wrap:wrap;background:#fff;'
           . 'border:1px solid #eef2f4;border-radius:10px;padding:18px">';
        foreach ($rows as $r) {
            if (trim((string)$r['image']) === '') continue;
            echo '<img src="../assets/img/' . e($r['image']) . '" alt="" '
               . 'style="height:52px;width:auto;object-fit:contain">';
        }
        echo '</div>';
    } else {
        echo '<p class="muted">Пусто.</p>';
    }
    echo '</div>';
}

/* ---------------- LINKS ---------------- */
elseif ($section === 'links') {
    echo '<form method="post" class="panel">' . csrf_field() . '<input type="hidden" name="action" value="save">';
    echo '<h3>Полезные ссылки</h3><p class="muted">Показываются на странице /linkler и в подвале. Чтобы удалить — очистите «Название».</p>';
    bulk_table($db, 'links', 'r', ['title' => 'Название', 'url' => 'Адрес', 'descr' => 'Описание']);
    echo '<div style="margin-top:16px"><button class="btn">Сохранить</button></div></form>';
}

/* ---------------- CONTACTS ---------------- */
elseif ($section === 'contacts') {
    $g = fn($k) => e(kvl_get('contacts', $k, $L));
    echo '<form method="post" class="panel">' . csrf_field() . '<input type="hidden" name="action" value="save"><h3>Контактные данные</h3>';
    echo '<div class="row"><div><label>Телефон (как показывать)</label><input type="text" name="phone" value="' . $g('phone') . '"></div>';
    echo '<div><label>Телефон для ссылки tel: / WhatsApp</label><input type="text" name="phone_raw" value="' . $g('phone_raw') . '"></div></div>';
    echo '<label>E-mail</label><input type="text" name="email" value="' . $g('email') . '">';
    echo '<div class="row"><div><label>Город</label><input type="text" name="city" value="' . $g('city') . '"></div>';
    echo '<div><label>Адрес (коротко, для шапки)</label><input type="text" name="address" value="' . $g('address') . '"></div></div>';
    echo '<label>Адрес полностью (подвал, контакты)</label><textarea name="address_full">' . $g('address_full') . '</textarea>';
    echo '<div class="row"><div><label>График: будни</label><input type="text" name="hours_week" value="' . $g('hours_week') . '"></div>';
    echo '<div><label>График: суббота</label><input type="text" name="hours_sat" value="' . $g('hours_sat') . '"></div></div>';
    echo '<label>Google Maps — embed URL</label><textarea name="map">' . $g('map') . '</textarea>';
    echo '<fieldset><legend>Соцсети</legend>';
    echo '<label>Facebook</label><input type="text" name="facebook" value="' . $g('facebook') . '">';
    echo '<label>Instagram</label><input type="text" name="instagram" value="' . $g('instagram') . '">';
    echo '<label>WhatsApp</label><input type="text" name="whatsapp" value="' . $g('whatsapp') . '">';
    echo '</fieldset>';
    echo '<div style="margin-top:18px"><button class="btn">Сохранить контакты</button></div></form>';
}

/* ---------------- IMAGES ---------------- */
elseif ($section === 'images') {
    $root = dirname(__DIR__) . '/assets/img';
    echo '<form method="post" enctype="multipart/form-data" class="panel">' . csrf_field() . '<input type="hidden" name="action" value="upload">';
    echo '<h3>Загрузить файл</h3><label>Изображение или видео</label><input type="file" name="file" accept="image/*,video/*" required>';
    echo '<div style="margin-top:14px"><button class="btn">Загрузить</button></div>';
    echo '<p class="muted">jpg / png / webp / gif / svg / mp4 / webm. Файлы попадают в <code>assets/img/admin/</code>. Кликните по пути, чтобы скопировать.</p></form>';

    $files = [];
    if (is_dir($root)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile()) continue;
            $ext = strtolower($f->getExtension());
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'mp4', 'webm'], true)) continue;
            $files[] = ['p' => $f->getPathname(), 'm' => $f->getMTime(), 'v' => in_array($ext, ['mp4', 'webm'], true)];
        }
    }
    usort($files, fn($a, $b) => $b['m'] <=> $a['m']);

    echo '<div class="panel"><h3>Медиа сайта (' . count($files) . ')</h3><div class="grid-media">';
    foreach ($files as $m) {
        $rel = ltrim(str_replace('\\', '/', substr($m['p'], strlen($root))), '/');
        echo '<div style="border:1px solid #eef2f4;border-radius:10px;padding:8px;text-align:center">';
        if ($m['v']) echo '<video src="../assets/img/' . e($rel) . '" preload="metadata" style="width:100%;height:90px;object-fit:cover;border-radius:6px;background:#000"></video>';
        else echo '<img src="../assets/img/' . e($rel) . '" loading="lazy" style="width:100%;height:90px;object-fit:contain;background:#f4f7f8;border-radius:6px">';
        echo '<input type="text" readonly value="' . e($rel) . '" data-copy="1" title="Кликните, чтобы скопировать" style="width:100%;font-size:10.5px;margin-top:6px;padding:4px;cursor:pointer">';
        echo '</div>';
    }
    if (!$files) echo '<p class="muted">Файлов нет.</p>';
    echo '</div></div>';
}

/* ---------------- SUBMISSIONS ---------------- */
elseif ($section === 'submissions') {
    $rows = $db->query('SELECT * FROM submissions ORDER BY id DESC')->fetchAll();
    echo '<div class="panel"><div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;gap:12px;flex-wrap:wrap">';
    echo '<div class="muted">Всего: ' . count($rows) . ', новых: ' . unread_count() . '</div>';
    if (unread_count()) echo '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="allread"><button class="btn ghost sm">Отметить все прочитанными</button></form>';
    echo '</div>';
    echo '<div class="tablewrap"><table><tr><th>Дата</th><th>Имя</th><th>Телефон</th><th>E-mail</th><th>Тема</th><th>Сообщение</th><th></th></tr>';
    if (!$rows) echo '<tr><td colspan="7" class="muted">Заявок пока нет.</td></tr>';
    foreach ($rows as $r) {
        $bg = (int)$r['is_read'] ? '' : ' style="background:#eafaf1"';
        echo '<tr' . $bg . '><td class="muted" style="white-space:nowrap">' . e($r['created_at']) . '</td>';
        echo '<td>' . e(trim($r['name'] . ' ' . $r['surname'])) . '</td>';
        echo '<td style="white-space:nowrap">' . ($r['phone'] ? '<a href="tel:' . e($r['phone']) . '">' . e($r['phone']) . '</a>' : '—') . '</td>';
        echo '<td>' . ($r['email'] ? '<a href="mailto:' . e($r['email']) . '">' . e($r['email']) . '</a>' : '—') . '</td>';
        echo '<td>' . e($r['subject']) . '</td><td>' . nl2br(e($r['message'])) . '</td>';
        echo '<td class="right" style="white-space:nowrap">';
        if (!(int)$r['is_read']) echo '<form method="post" style="display:inline">' . csrf_field() . '<input type="hidden" name="action" value="read"><input type="hidden" name="id" value="' . $r['id'] . '"><button class="btn sm ghost">Прочитано</button></form> ';
        echo '<form method="post" style="display:inline" data-confirm="Удалить заявку?">' . csrf_field() . '<input type="hidden" name="action" value="del"><input type="hidden" name="id" value="' . $r['id'] . '"><button class="btn sm red">✕</button></form></td></tr>';
    }
    echo '</table></div></div>';
}

/* ---------------- SEO ---------------- */
elseif ($section === 'seo') {
    $st = $db->prepare('SELECT * FROM seo_pages WHERE lang = ? ORDER BY slug'); $st->execute([$L]); $rows = $st->fetchAll();
    echo '<form method="post" class="panel">' . csrf_field() . '<input type="hidden" name="action" value="save">';
    echo '<h3>SEO всех страниц</h3><p class="muted">Отдельно для услуг и новостей SEO задаётся в их карточках.</p>';
    foreach ($rows as $r) {
        echo '<div class="item"><b>' . e($r['slug'] === '/' ? '/ (главная)' : '/' . $r['slug']) . '</b>';
        echo '<label>Title</label><input type="text" name="title[' . e($r['slug']) . ']" value="' . e($r['title']) . '">';
        echo '<label>Meta description</label><textarea name="descr[' . e($r['slug']) . ']">' . e($r['descr']) . '</textarea></div>';
    }
    echo '<button class="btn">Сохранить</button></form>';

    /* --- Google Analytics --- */
    $ga = kv_get('settings', 'ga_id');
    echo '<form method="post" class="panel">' . csrf_field() . '<input type="hidden" name="action" value="save_ga">';
    echo '<h3>Google Analytics <span class="muted" style="font-weight:400;font-size:13px">— сейчас: <b>'
       . ($ga !== '' ? e($ga) : 'выключена') . '</b></span></h3>';
    echo '<p class="muted">Номер потока данных GA4. Код подключается на всех страницах сайта, '
       . 'в админ-панели — нет. Оставьте поле пустым, чтобы отключить аналитику.</p>';
    echo '<label>Идентификатор</label><input type="text" name="ga_id" value="' . e($ga)
       . '" placeholder="G-XXXXXXXXXX">';
    echo '<button class="btn">Сохранить</button></form>';
}

/* ---------------- SMTP ---------------- */
elseif ($section === 'smtp') {
    $g = fn($k) => e(kv_get('settings', $k));
    $mode = kv_get('settings', 'smtp_host') !== '' ? 'SMTP' : 'стандартная функция mail()';
    echo '<form method="post" class="panel">' . csrf_field() . '<input type="hidden" name="action" value="save">';
    echo '<h3>Отправка почты <span class="muted" style="font-weight:400;font-size:13px">— сейчас: <b>' . e($mode) . '</b></span></h3>';
    echo '<label>Куда приходят заявки с сайта</label><input type="text" name="mail_to" value="' . e(kv_get('texts', 'mail_to')) . '">';
    echo '<div class="row3"><div><label>SMTP-сервер</label><input type="text" name="smtp_host" value="' . $g('smtp_host') . '" placeholder="mail.caucasuscl.com"></div>';
    echo '<div><label>Порт</label><input type="text" name="smtp_port" value="' . $g('smtp_port') . '"></div>';
    echo '<div><label>Шифрование</label><select name="smtp_secure">';
    foreach (['tls' => 'STARTTLS (587)', 'ssl' => 'SSL/TLS (465)', 'none' => 'Без шифрования'] as $v => $l)
        echo '<option value="' . $v . '"' . (kv_get('settings', 'smtp_secure') === $v ? ' selected' : '') . '>' . $l . '</option>';
    echo '</select></div></div>';
    echo '<div class="row"><div><label>Пользователь (полный адрес почты)</label><input type="text" name="smtp_user" value="' . $g('smtp_user') . '"></div>';
    echo '<div><label>Пароль</label><input type="password" name="smtp_pass" placeholder="' . (kv_get('settings', 'smtp_pass') !== '' ? 'сохранён — оставьте пустым' : '') . '"></div></div>';
    echo '<div class="row"><div><label>Адрес отправителя</label><input type="text" name="smtp_from" value="' . $g('smtp_from') . '"></div>';
    echo '<div><label>Имя отправителя</label><input type="text" name="smtp_from_name" value="' . $g('smtp_from_name') . '"></div></div>';
    echo '<div style="margin-top:18px"><button class="btn">Сохранить</button></div></form>';

    echo '<form method="post" class="panel">' . csrf_field() . '<input type="hidden" name="action" value="test"><h3>Проверка отправки</h3>';
    echo '<p class="muted">Отправим тестовое письмо, чтобы убедиться, что настройки верные.</p>';
    echo '<div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap"><div style="flex:1;min-width:220px"><label>Адрес получателя</label><input type="email" name="test_to" value="' . e(current_admin()['email'] ?? '') . '" required></div><button class="btn ghost">Отправить тест</button></div></form>';

    echo '<div class="panel"><h3>Где взять данные</h3><p class="muted">В cPanel → <b>Email Accounts</b> → у нужного ящика нажмите <b>Connect Devices</b>. Там сервер, порт и шифрование. Пользователь — полный адрес почты, пароль — от этого ящика.</p></div>';
}

/* ---------------- SECURITY ---------------- */
elseif ($section === 'security') {
    $site = kv_get('settings', 'turnstile_site');
    $hasSecret = kv_get('settings', 'turnstile_secret') !== '';
    $on = ($site !== '' && $hasSecret);
    $test = str_starts_with($site, '1x00000000') || str_starts_with($site, '2x00000000') || str_starts_with($site, '3x00000000');
    echo '<form method="post" class="panel">' . csrf_field() . '<input type="hidden" name="action" value="save">';
    echo '<h3>Cloudflare Turnstile (капча) <span class="pill" style="background:' . ($on ? '#0f8a4f' : '#c9ced0') . ';color:#fff">' . ($on ? 'включена' : 'выключена') . '</span></h3>';
    echo '<p class="muted">Защищает формы на сайте и вход в эту панель от ботов.</p>';
    if ($test) echo '<div class="flash" style="background:#b45309">Сейчас стоят ТЕСТОВЫЕ ключи Cloudflare — капча пропускает всех. Замените на настоящие.</div>';
    echo '<label>Site Key (публичный)</label><input type="text" name="turnstile_site" value="' . e($site) . '">';
    echo '<label>Secret Key (секретный)</label><input type="password" name="turnstile_secret" placeholder="' . ($hasSecret ? 'сохранён — оставьте пустым, чтобы не менять' : '') . '">';
    echo '<p class="muted" style="margin-top:10px">Чтобы выключить капчу — очистите оба поля.</p>';
    echo '<div style="margin-top:16px"><button class="btn">Сохранить</button></div></form>';
    echo '<div class="panel"><h3>Где взять ключи</h3><ol class="muted" style="line-height:1.9"><li>Зайдите на <b>dash.cloudflare.com</b> → раздел <b>Turnstile</b>.</li><li>Нажмите <b>Add widget</b>.</li><li>Domain — укажите <b>caucasuscl.com</b>.</li><li>Widget Mode — <b>Managed</b>.</li><li>Скопируйте Site Key и Secret Key в поля выше.</li></ol></div>';
    echo '<div class="panel"><h3>Что уже защищено</h3><ul class="muted" style="line-height:1.9"><li>Пароли хранятся необратимым хэшем.</li><li>Все формы защищены от CSRF.</li><li>Проверка типа файлов при загрузке.</li><li>Ловушка для ботов (honeypot) в формах сайта.</li></ul></div>';
}

/* ---------------- USERS ---------------- */
elseif ($section === 'users') {
    $me = (int)current_admin()['id'];
    echo '<form method="post" class="panel">' . csrf_field() . '<input type="hidden" name="action" value="add"><h3>Добавить пользователя</h3>';
    echo '<p class="muted">«Администратор» — полный доступ. «Редактор» — только контент.</p>';
    echo '<div class="row"><div><label>Имя</label><input type="text" name="name"></div><div><label>E-mail</label><input type="email" name="email" required></div></div>';
    echo '<div class="row"><div><label>Пароль (мин. 8 символов)</label><input type="text" name="password" required></div>';
    echo '<div><label>Роль</label><select name="role"><option value="editor">Редактор</option><option value="admin">Администратор</option></select></div></div>';
    echo '<div style="margin-top:16px"><button class="btn">Добавить</button></div></form>';

    $rows = $db->query('SELECT * FROM admins ORDER BY id')->fetchAll();
    echo '<form method="post" class="panel"><h3>Все пользователи</h3><p class="muted">Поле пароля оставьте пустым, если менять не нужно.</p>' . csrf_field() . '<input type="hidden" name="action" value="save">';
    echo '<div class="tablewrap"><table><tr><th>Имя</th><th>E-mail</th><th>Роль</th><th>Новый пароль</th><th>Активен</th><th>Вход</th><th></th></tr>';
    foreach ($rows as $r) {
        $self = (int)$r['id'] === $me;
        echo '<tr><td><input type="text" name="u[' . $r['id'] . '][name]" value="' . e($r['name'] ?? '') . '"></td>';
        echo '<td>' . e($r['email']) . ($self ? ' <span class="pill">это вы</span>' : '') . '</td>';
        echo '<td><select name="u[' . $r['id'] . '][role]"' . ($self ? ' disabled' : '') . '><option value="admin"' . (($r['role'] ?? '') === 'admin' ? ' selected' : '') . '>Администратор</option><option value="editor"' . (($r['role'] ?? '') === 'editor' ? ' selected' : '') . '>Редактор</option></select></td>';
        echo '<td><input type="text" name="u[' . $r['id'] . '][password]" placeholder="—"></td>';
        echo '<td style="text-align:center"><input type="checkbox" name="u[' . $r['id'] . '][active]" ' . ((int)($r['active'] ?? 1) ? 'checked' : '') . ($self ? ' disabled' : '') . '></td>';
        echo '<td class="muted" style="white-space:nowrap">' . e($r['last_login'] ?? '—') . '</td>';
        echo '<td class="right">' . ($self ? '' : '<button class="btn sm red" name="delete_id" value="' . $r['id'] . '" formnovalidate data-confirm="Удалить пользователя?">Удалить</button>') . '</td></tr>';
    }
    echo '</table></div><div style="margin-top:16px"><button class="btn">Сохранить изменения</button></div></form>';
}

/* ---------------- PROFILE ---------------- */
elseif ($section === 'profile') {
    $a = current_admin();
    echo '<form method="post" class="panel" style="max-width:540px">' . csrf_field() . '<input type="hidden" name="action" value="save"><h3>Мой профиль</h3>';
    echo '<label>Имя</label><input type="text" name="name" value="' . e($a['name'] ?? '') . '">';
    echo '<label>E-mail</label><input type="email" name="email" value="' . e($a['email'] ?? '') . '">';
    echo '<p class="muted" style="margin-top:10px">Роль: <b>' . (($a['role'] ?? '') === 'admin' ? 'Администратор' : 'Редактор') . '</b></p>';
    echo '<div style="margin-top:16px"><button class="btn">Сохранить</button></div></form>';
    echo '<form method="post" class="panel" style="max-width:540px">' . csrf_field() . '<input type="hidden" name="action" value="passwd"><h3>Сменить пароль</h3>';
    echo '<label>Текущий пароль</label><input type="password" name="old" required>';
    echo '<label>Новый пароль</label><input type="password" name="new" required>';
    echo '<label>Повторите новый</label><input type="password" name="new2" required>';
    echo '<div style="margin-top:16px"><button class="btn">Изменить пароль</button></div></form>';
}

layout_bottom();
