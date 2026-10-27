# Caucasus Customs and Logistics — PHP sayt

caucasuscl.com saytının WordPress-siz, təmiz PHP versiyası. Dizayn: **V1 (korporativ / işıqlı)**.

## İşə salmaq

Lokal (PHP daxili serveri):

```bash
php -S localhost:8010 -t D:\test\caucasuscl D:\test\caucasuscl\router.php
```

Sonra brauzerdə: <http://localhost:8010>

Hostinqdə (Apache): qovluğu `public_html`-ə atmaq kifayətdir — `.htaccess` marşrutlaşdırmanı özü qurur.
`mod_rewrite` aktiv olmalıdır. Alt qovluqda yerləşdirsəniz:

* `includes/config.php` → `define('BASE_URL', '/qovluq-adi');`
* `.htaccess` → `RewriteBase /qovluq-adi/` sətrini açın.

## Struktur

```
caucasuscl/
├── index.php               Ana səhifə
├── haqqimizda.php          Şirkət haqqında
├── xidmetler.php           Xidmətlərin siyahısı
├── xidmet.php              Xidmət detalı (?slug=…) — 7 səhifə
├── xeberler.php            Xəbərlərin siyahısı
├── xeber.php               Xəbər detalı (?slug=…) — 6 səhifə
├── elaqe.php               Əlaqə + xəritə + FAQ
├── linkler.php             Faydalı linklər
├── 404.php
├── router.php              php -S üçün marşrutlayıcı
├── .htaccess               Apache üçün ChPU + keş + gzip
├── includes/
│   ├── config.php          ⚙ BÜTÜN MƏTNLƏR VƏ SİYAHILAR BURADA
│   ├── functions.php       e(), url(), img(), icon(), az_date()…
│   ├── header.php          Topbar + menyu
│   ├── footer.php          Futer + WhatsApp düyməsi
│   ├── form.php            Formaların emalı və validasiyası
│   ├── contact-section.php Təkrarlanan "Bizimlə əlaqə" bloku
│   └── side.php            Daxili səhifələrin yan paneli
├── assets/
│   ├── css/style.css       Bütün üslublar (adaptiv daxil)
│   ├── js/main.js          Menyu, slayder, sayğaclar, animasiyalar
│   └── img/                Lokal şəkillər (24 fayl)
├── tools/
│   └── download-images.php Şəkilləri caucasuscl.com-dan endirir
└── storage/
    └── muracietler.log     mail() işləmədikdə ehtiyat qeyd
```

## Marşrutlar (orijinal saytla eyni)

Ünvanlar `includes/routes.php` faylında təyin olunur. Şablonlar kanonik yol yazır
(`url('/haqqimizda')`), `url()` onu cari dilin real ünvanına çevirir.

| Bölmə | AZ | RU | EN |
|---|---|---|---|
| Ana səhifə | `/` | `/ru/` | `/en/` |
| Haqqımızda | `/haqqimizda/` | `/ru/o-nas/` | `/en/about-us/` |
| Xidmətlər | `/xidmetler/` | `/ru/uslugi/` | `/en/services/` |
| Xidmət detalı | `/xidmetler/<slug>/` | `/ru/xidmetler/<slug>/` | `/en/services/<slug>/` |
| Xəbərlər | `/gomruk-xeberleri/` | `/ru/novosti/` | `/en/news/` |
| Xəbər detalı | `/<slug>/` (kökdə) | `/ru/novosti/<slug>/` | `/en/news/<slug>/` |
| Əlaqə | `/elaqe/` | `/ru/kontakty/` | `/en/contact/` |
| Faydalı linklər | `/faydali-linkler/` | `/ru/poleznye-ssylki/` | `/en/useful-links/` |

**Vacib:** AZ xəbərləri kökdədir — WordPress-dəki kimi. RU xidmət slug-ları da
orijinaldakı kimidir (`logistika-xidmeti`, `konsiqnasiya-isleri` və s.), ona görə
tərcümələr `tkey` sütunu ilə bağlanır.

Bütün ünvanlar sonda slash ilə bitir. Slash-sız ünvan 301 ilə yönləndirilir.
Köhnə `/category/xeberler/` və bizim aralıq `/xeberler/` sxemi də 301 verir.

### SEO

`php tools/verify-seo.php` — orijinal saytın sitemap-ındakı 62 ünvanı bizim saytla
müqayisə edir: status, `<title>` uyğunluğu, description-un boş olmaması.

SEO-nu orijinaldan köçürmək / boşları doldurmaq:
`/admin/import-seo.php?token=…`

Bütün xəbərləri orijinaldan gətirmək: `/admin/import-news.php?token=…`

## Məzmunu necə dəyişmək

Hər şey `includes/config.php` faylındadır — şablonlara toxunmağa ehtiyac yoxdur:

* **Əlaqə məlumatları** → `$CONTACT`
* **Rəqəmlər** (18 / 30+ / 18 / 150000+) → `$STATS`
* **Menyu** → `$NAV`
* **Xidmətlər** (başlıq, mətn, şəkil, SEO) → `$SERVICES`
* **Xəbərlər** (sual, YouTube ID, tarix) → `$NEWS`
* **Faydalı linklər** → `$USEFUL_LINKS`
* **Müştəri loqoları** → `$CLIENTS`
* **Proses addımları** → `$PROCESS`

Yeni xidmət əlavə etmək üçün `$SERVICES`-ə bir element yazmaq kifayətdir — səhifə, menyu,
yan panel və futer avtomatik yenilənir.

## Şəkillər

Şəkillər artıq `assets/img/` içindədir və `IMG_BASE` ora baxır.
Yenidən endirmək lazım olsa:

```bash
php tools/download-images.php
```

## Xidmət şəkilləri

`assets/img/services/` — 7 müasir foto (Unsplash, 1600×1000).
Lisenziya və şəkil ID-ləri: `assets/img/services/LICENSE.txt`.
Unsplash License kommersiya istifadəsinə icazə verir, istinad tələb etmir.

Köhnə klipart şəkillər silinməyib (`assets/img/2022/08/`, `kon.jpg`) — geri qaytarmaq
lazım olsa panelin «Услуги» bölməsində yolu dəyişmək kifayətdir.

Şəkli dəyişmək: panel → **Услуги** → xidməti seçin → «Загрузить новую картинку».
Şəkil bütün dillərdə ortaqdır — bir dildə dəyişsəniz, digərlərində də dəyişməlisiniz.

## Forma

`includes/form.php` — validasiya + bot tələsi (honeypot) + **Cloudflare Turnstile** + `mail()`.
Lokal serverdə `mail()` işləmədiyi üçün müraciət `storage/muracietler.log` faylına yazılır.
Hostinqdə ünvanı dəyişmək: `config.php` → `define('MAIL_TO', '…')`.

SMTP lazım olsa PHPMailer əlavə edib `form.php`-də `mail()` çağırışını əvəz etmək kifayətdir.

### Cloudflare Turnstile

Real açarlar bazadadır — admin paneli → **Безопасность** bölməsi (`settings.turnstile_site` / `settings.turnstile_secret`).
`includes/config.php`-dəki `TURNSTILE_*` sabitləri boşdur və yalnız baza əlçatmaz olduqda ehtiyat rolunu oynayır; boş qaldıqda kapça söndürülür.

Yeni açar almaq üçün: <https://dash.cloudflare.com> → **Turnstile** → *Add site*.

Yoxlama serverdə aparılır (`turnstile_verify()`), ona görə hostinqdən
`challenges.cloudflare.com`-a çıxış açıq olmalıdır.

## Dillər (AZ / RU / EN)

Sayt üç dildədir. URL sxemi:

| Dil | Ünvan |
|---|---|
| AZ (əsas) | `/xidmetler` |
| RU | `/ru/xidmetler` |
| EN | `/en/xidmetler` |

Yol seqmentləri bütün dillərdə eynidir — yalnız prefiks dəyişir. Buna görə dil
düyməsi istifadəçini **eyni səhifədə** saxlayır və tərcümə çatmayanda link sınmır.

### Nə haradadır

* **İnterfeys sətirləri** (düymələr, menyu, forma etiketləri) — `includes/lang/az.php`,
  `ru.php`, `en.php`. Şablonlarda `t('btn.send')` kimi çağırılır.
  Açar bir dildə yoxdursa avtomatik AZ-dan götürülür.
* **Məzmun** (xidmətlər, xəbərlər, mətnlər, əlaqə, SEO) — bazada, hər sətir öz `lang`
  sütunu ilə. Admin panelində yuxarıdakı **AZ / RU / EN** düyməsi ilə seçilir.
* **Dil aşkarlanması** — `includes/lang.php`, `REQUEST_URI`-dən. Həm Apache, həm də
  `php -S` ilə işləyir.

### Dil seçici

Yuxarı zolaqda açılan siyahıdır (`lang_selector()` — `includes/functions.php`).
Mobil menyuda loqonun yanında yerləşir. Kənara klik və Escape ilə bağlanır.
Hər variant istifadəçini **eyni səhifədə** saxlayır.

### Mobil menyu

Tam ekran, **skrolsuz**: `nav.main{height:100dvh;overflow:hidden}`, linklər blokunun
boşluqları `clamp()` ilə ekranın hündürlüyünə uyğunlaşır. Çox alçaq ekranlarda
(landşaft, ~420px) yalnız linklər siyahısı daxildən sürüşür — məzmun heç vaxt kəsilmir.

### Yeni dil əlavə etmək

1. `includes/lang.php` → `LANGS` massivinə sətir əlavə edin.
2. `includes/lang/<kod>.php` faylını yaradın (az.php-ni kopyalayıb tərcümə edin).
3. `router.php` və `.htaccess`-də prefiks siyahısına kodu əlavə edin.
4. `admin/lib.php` → `CMS_LANGS`-a əlavə edin.
5. Panel vasitəsilə məzmunu doldurun.

### Tərcümələr haradan gəlib

Rus mətnləri saytın işlək `caucasuscl.com/ru/` versiyasından götürülüb — yəni
şirkətin öz formulələridir. İngilis versiyası onların əsasında hazırlanıb və
**dil daşıyıcısı tərəfindən yoxlanılmalıdır**.

## Admin panel

Ünvan: **/admin/** — məsələn <http://localhost:8010/admin/>

İlk giriş: `admin@caucasuscl.com`. Parol quraşdırma anında `admin/config.php` →
`install_admin_pass` sahəsindən götürülür; defolt parol yoxdur və burada
yazılmır. Girişdən sonra onu «Мой профиль» bölməsində dəyişin.

### Quraşdırma

Baza avtomatik yaradılmır. Bir dəfə açın:

```
/admin/install.php?token=<install_token>
```

Skript cədvəlləri yaradır, `includes/config.php`-dəki bütün məzmunu bazaya köçürür və ilk admin
hesabını açır. Bitəndən sonra `install.php` faylını serverdən silin. Token `admin/config.php`-dədir.

Sonra bir dəfə **migrate.php** işə salın — baza çoxdilli sxemə keçir və RU/EN məzmunu dolur:

```
/admin/migrate.php?token=<install_token>
```

Təkrar işə salmaq təhlükəsizdir — mövcud sətirlərə toxunmur.

### Baza

`admin/config.php` → `driver`:

* `sqlite` (defolt) — heç nə quraşdırmaq lazım deyil, baza `storage/admin.sqlite` faylıdır.
* `mysql` — cPanel hostinqi üçün: MySQL Databases bölməsində baza yaradın və `db_*` sətirlərini doldurun.

Kod hər iki driver ilə eyni işləyir.

### Bölmələr

| Bölmə | Nə idarə olunur |
|---|---|
| Обзор | statistika, sistem vəziyyəti |
| *(dil düyməsi)* | Тексты, Страницы, Услуги, Новости, Цифры, Ссылки, SEO — hər biri AZ/RU/EN üzrə ayrıca |
| Тексты сайта | ad, slogan, hero, bütün bölmə başlıqları, «Haqqımızda» bloku |
| Страницы | səhifələrin SEO title / description / robots / canonical |
| Услуги | 7 xidmət: şəkil, mətn, SEO, sıra, görünürlük |
| Новости | sual, YouTube video, tarix, qısa mətn |
| Цифры и блоки | sayğaclar, «4 addım», «Niyə biz» kartları |
| Клиенты | loqolar |
| Полезные ссылки | dövlət orqanlarının siyahısı |
| Изображения | fayl yükləmə və media kitabxanası |
| Контакты и соцсети | telefon, email, ünvan, qrafik, xəritə, sosial şəbəkələr |
| Заявки с сайта | formadan gələn müraciətlər (oxunmuş / silmək) |
| SEO | bütün səhifələrin meta məlumatı bir siyahıda |
| Почта (SMTP) | müraciətlərin göndərildiyi ünvan, SMTP və test məktubu |
| Безопасность | Cloudflare Turnstile açarları |
| Пользователи | admin / redaktor hesabları |
| Мой профиль | ad, email, parol |

### Xidmət mətninin formatı

«Содержание страницы» sahəsində sadə işarələr:

```
## Bölmə başlığı
Adi sətir — abzas
- pünkt — işarəli siyahı
* Başlıq | Təsvir — kart
```

### Sayt ilə əlaqə

`includes/content.php` bazadakı dəyərləri `includes/config.php`-dəki massivlərin üzərinə yazır.
Baza yoxdursa və ya sıradan çıxsa — sayt config.php-dəki ilkin dəyərlərlə işləməyə davam edir.

Turnstile açarları və müraciət ünvanı da əvvəlcə paneldən, sonra config.php-dən götürülür.
Saytın formasından gələn hər müraciət `submissions` cədvəlinə düşür.

## Serverdə (caucasuscl.com)

Sayt 26.08.2026-da canlı serverə köçürüldü.

| | |
|---|---|
| Yer | `/home/caucasuscl/public_html` (cPanel, LiteSpeed) |
| PHP | **7.4.33** — `includes/compat.php` PHP 8 funksiyalarını əvəz edir |
| Baza | MySQL — ad, istifadəçi və parol `admin/config.php`-dədir (`driver => 'mysql'`) |
| Admin | `https://caucasuscl.com/admin/` |
| Köhnə WordPress arxivləri | `/home/caucasuscl/wp-backup/` (veb-dən açılmır) |
| Köhnə media | `public_html/wp-content/uploads/` — şəkil ünvanları qırılmasın deyə saxlanılıb |

### PHP 7.4 haqqında

Server hələ PHP 7.4 işlədir (dəstəyi bitib). `includes/compat.php`
`str_starts_with()`, `str_ends_with()`, `str_contains()` funksiyalarını və
saat qurşağını (`Asia/Baku`) əlavə edir, ona görə sayt işləyir.
**cPanel → MultiPHP Manager**-dən PHP 8.2-yə keçmək tövsiyə olunur —
kod hər iki versiyada işləyir, compat.php isə sadəcə heç nə etmir.

### Yenidən yükləmək

Fayllar FTP ilə yüklənir. Yüklənməyənlər: `tools/`, `router.php`,
`README.md`, `.gitignore`, `storage/admin.sqlite`, `admin/config.php`
(serverdə öz MySQL variantı var), `admin/{install,migrate*,import-*,seed-i18n}.php`.

Bazanı yeniləmək üçün lokal SQLite-dan JSON çıxarışı hazırlanıb serverdə
birdəfəlik skriptlə yüklənir (skript sonra silinir).

### Yoxlama

```
php tools/verify-seo.php            # orijinal sayt ilə müqayisə (arxivdən)
php tools/compare-live.php          # lokal ↔ canlı: status, title, description
```

`tools/compare-live.php` lokal sitemap-dakı 150 ünvanı canlı saytla tutuşdurur.

## Görülməmiş işlər

* **İngilis mətnləri** — dil daşıyıcısı yoxlamalıdır.
* **Rus/ingilis xəbər tərcümələri** — 37 xəbərin hamısı tərcümə olunub;
  prezident fərmanlarının rəsmi mətnləri hüquqşünas tərəfindən yoxlanılmalıdır.
* **Admin parolu** — quraşdırma zamanı qoyulan parol hələ qüvvədədir, ilk
  fürsətdə paneldən dəyişilməlidir.
* **SMTP** — hazırda `mail()` istifadə olunur; panelin SMTP bölməsi doldurulmayıb.
* **PHP 8-ə keçid** — cPanel → MultiPHP Manager.
