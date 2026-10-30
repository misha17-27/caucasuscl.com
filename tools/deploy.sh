#!/bin/bash
#
# cPanel Git Version Control → «Deploy HEAD Commit» bu skripti çağırır
# (.cpanel.yml-də yalnız bir sətir var, çünki cPanel-in YAML oxuyucusu
# köhnədir və mürəkkəb və ya qeyri-ASCII məzmunda imtina edir).
#
# Nə edir:
#   1. repozitoriyadakı faylları public_html-ə köçürür
#   2. çatışmayan məqalə sətirlərini bazaya əlavə edir
#
# DİQQƏT: silmə YOXDUR. public_html-də repozitoriyada olmayan, lakin saytın
# işləməsi üçün vacib fayllar var:
#   admin/config.php      — baza parolu, Turnstile və SMTP açarları
#   storage/              — baza faylı və jurnallar
#   assets/img/admin/     — paneldən yüklənmiş şəkillər
#   wp-content/uploads/   — köhnə WordPress şəkilləri (köhnə linklər sınmasın)
#
set -u

SRC="$(cd "$(dirname "$0")/.." && pwd)"
DP="${DEPLOY_PATH:-$HOME/public_html}"

echo "== caucasuscl.com yerlesdirme =="
echo "menbe : $SRC"
echo "hedef : $DP"

if [ ! -d "$DP" ]; then
    echo "XETA: hedef qovluq yoxdur -> $DP"
    exit 1
fi
if [ ! -f "$SRC/index.php" ]; then
    echo "XETA: menbe duzgun deyil, index.php tapilmadi -> $SRC"
    exit 1
fi

# ---------------------------------------------------------------- 1. fayllar
#
# Repozitoriyaya aid olub serverə lazım olmayanlar.
EXCL=".git .github .claude .gitignore .gitattributes .cpanel.yml README.md router.php"
#
# SERVERİN ÖZ FAYLLARI — heç vaxt üzərinə yazılmır.
# Bunlar .gitignore-dadır, yəni cPanel-in klonunda onsuz da yoxdur; lakin
# skript işlək nüsxədən (məsələn tərtibatçının kompüterindən) işə salınsa,
# yerli parollar və yerli baza serverdəkini əvəz edərdi. Sınaqda məhz bu baş
# verdi, ona görə istisna açıq yazılıb.
EXCL="$EXCL admin/config.php storage assets/img/admin wp-content"
#
# BİRDƏFƏLİK quraşdırma skriptləri. Repozitoriyada tarix üçün qalırlar, lakin
# quraşdırma bitəndən sonra serverdən silinmişdilər — hər yerləşdirmədə geri
# qayıtmamalıdırlar. Hamısı açarla qorunur, amma işlək saytda yerləri yoxdur.
EXCL="$EXCL admin/install.php admin/migrate.php admin/migrate2.php admin/migrate3.php"
EXCL="$EXCL admin/import-news.php admin/import-seo.php admin/seed-data.php admin/seed-i18n.php"

# public_html-in OZ icazeleri qorunur.
#
# 31.10.2026-da sayt məhz buna görə yatdı: `rsync -rlptD "$SRC/" "$DP/"`
# mənbə qovluğunun icazələrini hədəf qovluğuna da tətbiq edir, repozitoriya
# kökü isə 700-dür (cPanel onu belə klonlayır). Nəticədə public_html 700 oldu,
# veb server (qrup `nobody`) içəri girə bilmədi və bütün sayt 403 verdi.
# `tar` də eyni şeyi edir, çünki arxivdə `./` yazısı var.
# Ona görə: icazələr əvvəlcədən yadda saxlanılır və köçürmədən sonra bərpa olunur.
DP_MODE="$(stat -c '%a' "$DP" 2>/dev/null || echo '')"

if command -v rsync >/dev/null 2>&1; then
    OPTS=""
    for e in $EXCL; do OPTS="$OPTS --exclude=$e"; done
    # -rltD: `p` QƏSDƏN yoxdur — mənbənin icazələri köçürülmür, yeni fayllar
    # umask-dan normal icazə alır. owner/group da köçürülmür (paylaşılan hostda
    # uğursuz olur).
    # shellcheck disable=SC2086
    rsync -rltD $OPTS "$SRC/" "$DP/" || { echo "XETA: rsync ugursuz"; exit 1; }
    echo "fayllar kopyalandi (rsync)"
else
    echo "rsync yoxdur, tar ile kopyalanir"
    TAR_EXCL=""
    for e in $EXCL; do TAR_EXCL="$TAR_EXCL --exclude=./$e"; done
    # shellcheck disable=SC2086
    ( cd "$SRC" && tar cf - $TAR_EXCL . ) | ( cd "$DP" && tar xf - ) \
        || { echo "XETA: tar ile kopyalama ugursuz"; exit 1; }
    echo "fayllar kopyalandi (tar)"
fi

if [ -n "$DP_MODE" ]; then
    NOW="$(stat -c '%a' "$DP" 2>/dev/null || echo '')"
    if [ "$NOW" != "$DP_MODE" ]; then
        chmod "$DP_MODE" "$DP" && echo "hedef qovlugun icazeleri berpa olundu ($NOW -> $DP_MODE)"
    fi
else
    echo "XEBERDARLIQ: stat islemedi, $DP icazeleri yoxlanilmadi."
    echo "Sayt 403 verirse: chmod 750 $DP"
fi

# ---------------------------------------------------------------- 2. meqaleler
# cPanel-de /usr/local/bin/php domenin secilmis versiyasi olmaya biler.
PHPBIN=""
for p in /opt/cpanel/ea-php74/root/usr/bin/php \
         /opt/cpanel/ea-php82/root/usr/bin/php \
         /opt/cpanel/ea-php81/root/usr/bin/php \
         /opt/cpanel/ea-php80/root/usr/bin/php \
         /usr/local/bin/php /usr/bin/php; do
    [ -x "$p" ] && PHPBIN="$p" && break
done

if [ -z "$PHPBIN" ]; then
    echo "XEBERDARLIQ: PHP ikiliyi tapilmadi, meqale importu atlanildi."
    echo "Fayllar yerindedir, sayt isleyir. Importu elle isledin."
    exit 0
fi

echo "php   : $PHPBIN ($("$PHPBIN" -r 'echo PHP_VERSION;' 2>/dev/null))"

if [ ! -f "$DP/admin/config.php" ]; then
    echo "XEBERDARLIQ: $DP/admin/config.php yoxdur, import atlanildi."
    exit 0
fi

# --only-new: paneldan edilmis redakte her deploy-da geri qaytarilmasin.
"$PHPBIN" "$DP/tools/import-articles.php" --only-new
echo "== bitdi =="
