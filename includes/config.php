<?php
/**
 * Caucasus Customs and Logistics — sayt konfiqurasiyası və məzmun bazası.
 * Bütün mətnlər və siyahılar burada saxlanılır ki, şablonlar təmiz qalsın.
 */

// ---------------------------------------------------------------- ƏSAS
define('SITE_NAME',   'Caucasus Customs and Logistics');
define('SITE_SHORT',  'Caucasus CL');
define('SITE_SLOGAN', 'Sizin güvənli gömrük təmsilçiniz');
define('BASE_URL',    ''); // alt qovluqda yerləşdirsəniz: '/caucasuscl'

// Şəkillər lokal saxlanılır (php tools/download-images.php ilə endirilib).
// Yenidən uzaq serverdən götürmək üçün: define('IMG_BASE', 'https://caucasuscl.com/wp-content/uploads');
define('IMG_BASE', BASE_URL . '/assets/img');

// Formadan gələn müraciətlərin göndəriləcəyi ünvan
define('MAIL_TO', 'info@caucasuscl.com');

// Göndərilən məktubun domeni. Sabitdir — HTTP_HOST başlığı ziyarətçi
// tərəfindən dəyişdirilə bildiyi üçün məktub başlığında istifadə olunmur.
define('MAIL_DOMAIN', 'caucasuscl.com');

/* Dil (AZ / RU / EN) — massivlərdən əvvəl yüklənməlidir */
require_once __DIR__ . '/lang.php';

/* ------------------------------------------------------------------
   Cloudflare Turnstile (kapça)
   Açarlar bazada saxlanılır — admin panelində «Parametrlər» bölməsində
   (turnstile_site / turnstile_secret). Buradakı sətirlər yalnız baza
   olmadıqda ehtiyat variantdır; boş olduqda kapça söndürülür.
   Yeni açar almaq üçün: https://dash.cloudflare.com → Turnstile → Add site.
------------------------------------------------------------------ */
define('TURNSTILE_SITE_KEY',   '');
define('TURNSTILE_SECRET_KEY', '');
define('TURNSTILE_ENABLED',    TURNSTILE_SITE_KEY !== '' && TURNSTILE_SECRET_KEY !== '');

$CONTACT = [
    'phone'      => '(+994) 70 548 01 10',
    'phone_raw'  => '+994705480110',
    'email'      => 'info@caucasuscl.com',
    'city'       => 'Bakı şəhəri',
    'address'    => 'Heydər Əliyev Prospekti 95a',
    'address_full' => 'Bakı, Heydər Əliyev Prospekti 95a (Qafqaz Biznes Mərkəzi), 2-ci mərtəbə, 202-ci kabinet',
    'hours_week' => 'B.e–Cümə: 9:00 – 18:00',
    'hours_sat'  => 'Şənbə: 09:00 – 15:00',
    'facebook'   => 'https://www.facebook.com/Caucasus-Customs-and-Logistics-104745035224658/',
    'instagram'  => 'https://www.instagram.com/caucasus.customs.and.logistics/',
    'whatsapp'   => 'https://api.whatsapp.com/send?phone=+994705480110',
    'map'        => 'https://www.google.com/maps?q=Heydar+Aliyev+prospekti+95a+Baku&output=embed',
];

$STATS = [
    ['n' => 18,     'suffix' => '',  'label' => 'İllik təcrübə'],
    ['n' => 30,     'suffix' => '+', 'label' => 'Daimi müştəri'],
    ['n' => 18,     'suffix' => '',  'label' => 'Fərqli sahədə'],
    ['n' => 150000, 'suffix' => '+', 'label' => 'Görülən işlər'],
];

// ---------------------------------------------------------------- NAVİQASİYA
$NAV = [
    ['t' => t('nav.home'),     'u' => '/',            'key' => 'home'],
    ['t' => t('nav.about'),    'u' => '/haqqimizda',  'key' => 'about'],
    ['t' => t('nav.services'), 'u' => '/xidmetler',   'key' => 'services'],
    ['t' => t('nav.news'),     'u' => '/xeberler',    'key' => 'news'],
    ['t' => t('nav.links'),    'u' => '/linkler',     'key' => 'links'],
    ['t' => t('nav.contact'),  'u' => '/elaqe',       'key' => 'contact'],
];

$USEFUL_LINKS = [
    ['t' => 'Dövlət Gömrük Komitəsi',                  'u' => 'https://customs.gov.az/',        'd' => 'Gömrük qanunvericiliyi, tarif cədvəlləri, elektron xidmətlər və rəsmi izahlar.'],
    ['t' => 'Elektron Hökumət Portalı',                'u' => 'https://www.e-gov.az/',          'd' => 'Dövlət orqanlarının onlayn xidmətlərinə vahid giriş nöqtəsi.'],
    ['t' => 'Dövlət Vergi Xidməti',                    'u' => 'https://www.taxes.gov.az/az',    'd' => 'ƏDV, aksiz və digər vergi öhdəlikləri üzrə məlumat və e-xidmətlər.'],
    ['t' => 'Qida Təhlükəsizliyi Agentliyi',           'u' => 'http://afsa.gov.az/az/home',     'd' => 'Qida və kənd təsərrüfatı məhsullarının idxalı üzrə icazə və sertifikatlar.'],
    ['t' => 'Daxili İşlər Nazirliyi',                  'u' => 'https://mia.gov.az/',            'd' => 'Nəqliyyat vasitələrinin qeydiyyatı və xüsusi icazələr.'],
    ['t' => 'Rəqəmsal İnkişaf və Nəqliyyat Nazirliyi', 'u' => 'https://mincom.gov.az/az/',      'd' => 'Nəqliyyat, rabitə və informasiya texnologiyaları sahəsində tənzimləmə.'],
    ['t' => 'Fövqəladə Hallar Nazirliyi',              'u' => 'https://www.fhn.gov.az/',        'd' => 'Təhlükəli yüklər və sənaye təhlükəsizliyi üzrə icazə sənədləri.'],
    ['t' => 'Səhiyyə Nazirliyi',                       'u' => 'https://www.sehiyye.gov.az/',    'd' => 'Dərman vasitələri və tibbi ləvazimatın idxalı üzrə rəy və icazələr.'],
];

$CLIENTS = ['aaaa.jpg','sara.jpg','proelektro.jpg','kraun.jpg','toxum.jpg','vetart.jpg','1-3.jpg','2-1.jpg','3-1.jpg','4-1.jpg'];

$PROCESS = [
    ['n' => '01', 't' => 'Müraciət və analiz',        'd' => 'Yükün növü, mənşə ölkəsi və sənədləri üzrə ilkin qiymətləndirmə aparırıq.'],
    ['n' => '02', 't' => 'Sənədlərin hazırlanması',   'd' => 'Zəruri sənədləri toplayır, sertifikat və icazələri alırıq.'],
    ['n' => '03', 't' => 'Bəyannamə və rəsmiləşdirmə','d' => 'Bəyannaməni tərtib edir, rüsumları hesablayır və postda müşayiət edirik.'],
    ['n' => '04', 't' => 'Təhvil və çatdırılma',      'd' => 'Yükü anbardan təhvil alır və göstərdiyiniz ünvana çatdırırıq.'],
];

// ---------------------------------------------------------------- XİDMƏTLƏR
$SERVICES = [

'gomruk-beyannameleri' => [
    'no'    => '01',
    'title' => 'Gömrük bəyannamələrinin tərtib olunması',
    'short' => 'İdxal, ixrac və tranzit bəyannamələrinin səhvsiz tərtibi, elektron sistemə yüklənməsi və müşayiəti.',
    'img'   => 'services/gomruk-beyannameleri.jpg',
    'meta_title' => 'Gömrük bəyannamələrinin hazırlanması Bakıda | Sürətli xidmət',
    'meta_desc'  => 'Bakıda gömrük bəyannamələrinin hazırlanması və rəsmiləşdirilməsi. İdxal, ixrac və tranzit bəyannamələri üzrə sürətli və düzgün xidmət.',
    'lead'  => 'Bakıda gömrük bəyannamələrinin hazırlanması idxal və ixrac əməliyyatlarının əsas mərhələlərindən biridir. Şirkətimiz gömrük rəsmiləşdirilməsi prosesini sürətli və düzgün şəkildə həyata keçirir — bəyannamənin düzgün hazırlanması malların gecikmədən sərhəddən keçməsi üçün həlledici əhəmiyyət daşıyır.',
    'sections' => [
        [
            'h' => 'Gömrük bəyannaməsi nədir və niyə tərtib olunur?',
            'p' => [
                'Gömrük bəyannaməsi — şirkətin xarici ölkələrlə ticarət edərkən dövlət tərəfindən tələb olunan rəsmi sənəddir. Bəyannamə gömrük orqanlarına malın nə olduğu, hansı ölkədən gəldiyi, hansı mənsubiyyətə aid olduğu və dəyərinin nə qədər olması barədə məlumat verir.',
                'Həmçinin gömrük bəyannaməsi vergi və gömrük rüsumlarının hesablanması üçün əsas mənbədir. Bu sənəddəki hər bir səhv həm gecikmə, həm də əlavə maliyyə yükü deməkdir.',
            ],
        ],
        [
            'h' => 'Bəyannamənin əsas elementləri',
            'items' => [
                ['t' => 'Malın təsviri',        'd' => 'Malın növü, miqdarı, çəkisi, ölçüsü və məhsulun texniki təsviri.'],
                ['t' => 'Gömrük tarif kodu',    'd' => 'Hər bir məhsulu tanımaq və tənzimləmək üçün istifadə olunan HS kod sistemi.'],
                ['t' => 'Müşayiət sənədləri',   'd' => 'Şirkətin identifikasiyası, malın mənbəyi və tərkibi haqqında sənədlər.'],
                ['t' => 'Gömrük dəyəri',        'd' => 'Rüsum və vergilərin hesablanması üçün əsas götürülən dəyər.'],
            ],
        ],
        [
            'h' => 'Tərtib prosesi',
            'p' => ['Gömrük bəyannaməsinin tərtibi diqqət və dəqiqlik tələb edir. Prosesin əsas addımları aşağıdakılardır:'],
            'list' => [
                'Məlumatların toplanması — malın təsviri, mənşəyi və dəyəri üzrə sənədlərin yığılması.',
                'Tarif kodunun müəyyənləşdirilməsi və rüsum dərəcəsinin dəqiqləşdirilməsi.',
                'Bəyannamənin elektron sistemdə tərtibi və yoxlanılması.',
                'Gömrük ödənişlərinin hesablanması və köçürülməsi.',
                'Gömrük postunda bəyannamənin müşayiəti və buraxılışın alınması.',
            ],
        ],
    ],
],

'idxal-ixrac-senedleri' => [
    'no'    => '02',
    'title' => 'İdxal və İxrac zamanı tələb olunan zəruri sənədlərin hazırlanması',
    'short' => 'Müqavilə, invoys, mənşə sertifikatı və digər zəruri sənədlərin toplanması, tərcüməsi və yoxlanılması.',
    'img'   => 'services/idxal-ixrac-senedleri.jpg',
    'meta_title' => 'İdxal və ixrac sənədlərinin hazırlanması Bakıda | Tam xidmət',
    'meta_desc'  => 'Bakıda idxal və ixrac sənədlərinin hazırlanması: ticarət fakturası, mənşə sertifikatı, nəqliyyat sənədləri və tərcümələr.',
    'lead'  => 'Bakıda idxal və ixrac sənədlərinin hazırlanması beynəlxalq ticarət prosesinin ən vacib hissəsidir. Şirkətimiz bütün zəruri sənədlərin düzgün və operativ hazırlanmasını təmin edir ki, yükünüz sərhəddə saxlanılmasın.',
    'sections' => [
        [
            'h' => 'Sənədlərin ümumi tələbləri',
            'p' => [
                'Azərbaycan bir çox xarici ticarət partnyoru ilə mövcud beynəlxalq ticarət qanunlarına uyğun şəkildə əməkdaşlıq edir. Bu, şirkətlər üçün geniş imkan yaradır, lakin mal və xidmətlərin idxalı və ixracı müvafiq sənədlərin düzgün hazırlanmasını tələb edir.',
                'Sənədlərin mütləq elementləri, tərcümələrin notarial təsdiqi və ölkəyə xüsusi tələblər — bunların hər biri ayrıca nəzarət tələb edir.',
            ],
        ],
        [
            'h' => 'İdxal sənədləri',
            'items' => [
                ['t' => 'Ticarət fakturası və qəbz', 'd' => 'İdxal məhsulunun və ödənişin qeydiyyatı üçün lazımdır; məhsulun qiymətini və miqdarını göstərir.'],
                ['t' => 'Alıcı və satıcı sənədləri', 'd' => 'Tərəflər arasında əlaqəni təsdiq edir və ödənişlərin qeydiyyatını aparır.'],
                ['t' => 'Nəqliyyat sənədləri',       'd' => 'CMR, konosament, aviabill və stansiyalararası sənədlər.'],
                ['t' => 'Mənşə sertifikatı',         'd' => 'Güzəştli tarif rejimlərindən yararlanmaq üçün əsas sənəd.'],
            ],
        ],
        [
            'h' => 'İxrac sənədləri',
            'list' => [
                'İxrac fakturasının hazırlanması və rəsmiləşdirilməsi.',
                'İxrac sənədlərinin müvafiq orqanlar tərəfindən təsdiq edilməsi.',
                'Təyinat ölkəsinin tələb etdiyi xüsusi sənədlərin hazırlanması.',
                'Zəruri hallarda tərcümə və notarial təsdiq.',
            ],
        ],
    ],
],

'sertifikatlar-ve-icazeler' => [
    'no'    => '03',
    'title' => 'Dövlət orqanlarından lazımı sertifikatların və icazə sənədlərin alınması',
    'short' => 'Uyğunluq, baytarlıq, fitosanitar və digər icazələrin dövlət orqanlarından operativ alınması.',
    'img'   => 'services/sertifikatlar-ve-icazeler.jpg',
    'meta_title' => 'Sertifikatların və icazələrin alınması Bakıda | Rəsmi xidmət',
    'meta_desc'  => 'Bakıda dövlət orqanlarından sertifikat və icazə sənədlərinin alınması. Uyğunluq, baytarlıq, fitosanitar icazələri üzrə tam müşayiət.',
    'lead'  => 'Sertifikatlar və icazə sənədləri bir çox sahədə fəaliyyət göstərən şəxs və təşkilatlar üçün vacibdir. Dövlət orqanlarından bu sənədləri əldə etmək bir sıra prosedur və təlimatların izlənməsini tələb edir — biz bu yolu sizin əvəzinizə gedirik.',
    'sections' => [
        [
            'h' => 'Prosesin addımları',
            'list' => [
                'Hansı növ sertifikat və ya icazə sənədinə ehtiyac olduğunu müəyyənləşdiririk.',
                'Tələblərə əsasən lazım olan sənəd paketini hazırlayırıq.',
                'Müvafiq dövlət orqanının müraciət qaydasını və müddətlərini dəqiqləşdiririk.',
                'Müraciət formalarını doldurur və sənədləri təqdim edirik.',
                'Dövlət rüsumlarının ödənişini təşkil edirik.',
                'Təsdiqlənmiş sertifikatı və ya icazəni əldə edib sizə təhvil veririk.',
            ],
        ],
        [
            'h' => 'Ən çox tələb olunan sənədlər',
            'items' => [
                ['t' => 'Uyğunluq sertifikatı', 'd' => 'Məhsulun texniki reqlament və standartlara uyğunluğunun təsdiqi.'],
                ['t' => 'Baytarlıq şəhadətnaməsi', 'd' => 'Heyvan mənşəli məhsulların idxalı üçün zəruri sənəd.'],
                ['t' => 'Fitosanitar sertifikat', 'd' => 'Bitki mənşəli məhsullar üçün tələb olunur.'],
                ['t' => 'Xüsusi icazələr', 'd' => 'Təhlükəli yüklər, dərman vasitələri və digər tənzimlənən mallar üçün.'],
            ],
        ],
    ],
],

'yuklerin-catdirilmasi' => [
    'no'    => '04',
    'title' => 'Yüklərin gömrük anbarından təhvil alınması və müştərinin ünvanına çatdırılması',
    'short' => 'Gömrük anbarından yükün təhvil alınması və müştərinin ünvanına təhlükəsiz çatdırılması.',
    'img'   => 'services/yuklerin-catdirilmasi.jpg',
    'meta_title' => 'Yüklərin çatdırılması Bakıda | Gömrükdən çıxarılma və logistika',
    'meta_desc'  => 'Bakıda yüklərin gömrük anbarından çıxarılması və ünvana çatdırılması. Sürətli və təhlükəsiz logistika həlləri.',
    'lead'  => 'Bakıda yüklərin çatdırılması və gömrük anbarından çıxarılması xidmətləri malların sürətli və təhlükəsiz şəkildə müştəriyə çatdırılmasını təmin edir. Şirkətimiz bu sahədə tam məsuliyyəti öz üzərinə götürür.',
    'sections' => [
        [
            'h' => 'Gömrük anbarından təhvil alma',
            'list' => [
                'Sifariş sənədi və gömrük sənədlərinin gömrük orqanına təqdim edilməsi.',
                'Gömrük orqanının tələb və prosedurlarına tam əməl olunması.',
                'Yükün qanuni qaydada və qorunmuş vəziyyətdə anbardan çıxarılması.',
            ],
        ],
        [
            'h' => 'Rəsmiləşdirmə və ödənişlər',
            'p' => ['Gömrük rəsmiləri tərəfindən yükün yoxlanılması, gömrük vergisi, rüsumlar və digər ödənişlərin hesablanaraq köçürülməsi prosesi həyata keçirilir. Yoxlama və ödənişlər düzgün aparıldıqdan sonra yük təhvil verilir.'],
        ],
        [
            'h' => 'Ünvana çatdırılma',
            'list' => [
                'Uyğun nəqliyyat və logistika tədbirlərinin təşkili.',
                'Yükün tam və sağlam olmasının yoxlanılması.',
                'Çatdırılmanın vaxtında və təhlükəsiz həyata keçirilməsi.',
            ],
        ],
    ],
],

'logistika-xidmetleri' => [
    'no'    => '05',
    'title' => 'Logistika',
    'short' => 'Dəniz, hava, dəmiryol və avtomobil daşımaları — optimal marşrut və şəffaf qiymət.',
    'img'   => 'services/logistika-xidmetleri.jpg',
    'meta_title' => 'Logistika xidmətləri Bakıda | Yük daşınması və çatdırılma',
    'meta_desc'  => 'Bakıda logistika xidmətləri: dəniz, hava, dəmiryol və avtomobil daşımaları, saxlama və sifarişlərin idarə edilməsi.',
    'lead'  => 'Logistika xidməti — məhsul və malların bir nöqtədən digərinə çatdırılması üçün təşkil edilmiş məsuliyyətli proseslər sistemidir. Bu xidmət istehsaldan başlayaraq saxlanma, daşınma və son çatdırılmaya qədər bütün mərhələləri əhatə edir.',
    'sections' => [
        [
            'h' => 'Logistika xidmətlərinin əhatə dairəsi',
            'items' => [
                ['t' => 'Daşıma',                    'd' => 'Malların dəniz, dəmir yolu, hava yolu və avtomobil nəqliyyatı ilə təhlükəsiz hərəkəti.'],
                ['t' => 'Saxlama',                   'd' => 'Anbar sahələrinin idarə edilməsi, optimallaşdırılması və tələbatın planlaşdırılması.'],
                ['t' => 'Sifarişlərin idarəsi',      'd' => 'Sifarişlərin qəbulu, təyin edilməsi və müştəriyə çatdırılması prosesləri.'],
                ['t' => 'İnformasiya idarəsi',       'd' => 'Məlumatların effektiv idarə olunması və paylanması üçün texnoloji həllər.'],
                ['t' => 'Təchizatın idarə edilməsi', 'd' => 'Malların alınması, təchizatın təşkili və tələbatın müəyyənləşdirilməsi.'],
            ],
        ],
        [
            'h' => 'Niyə bu vacibdir?',
            'p' => ['Düzgün qurulmuş logistika şirkətlərin məhsullarını daha sürətli, sərfəli və effektiv şəkildə müştərilərə çatdırmağa imkan verir. Bu, işin effektivliyini artırır və lazımsız xərcləri azaldır.'],
        ],
    ],
],

'konsaltinq-xidmeti' => [
    'no'    => '06',
    'title' => 'Konsaltinq',
    'short' => 'Gömrük qanunvericiliyi, rüsum hesablanması və risklərin idarə olunması üzrə peşəkar məsləhət.',
    'img'   => 'services/konsaltinq-xidmeti.jpg',
    'meta_title' => 'Gömrük və logistika konsaltinqi Bakıda | Peşəkar məsləhət',
    'meta_desc'  => 'Bakıda gömrük və logistika konsaltinqi: prosedurlar, idxal-ixracın optimallaşdırılması, risklərin və xərclərin azaldılması.',
    'lead'  => 'Bakıda gömrük və logistika konsaltinqi xidmətlərimiz idxal və ixrac əməliyyatlarının düzgün və effektiv idarə olunmasına kömək edir. Peşəkar komandamız biznesinizə uyğun praktiki həllər təqdim edir.',
    'sections' => [
        [
            'h' => 'Konsaltinq istiqamətlərimiz',
            'list' => [
                'Gömrük prosedurları və rəsmiləşdirmə üzrə məsləhət.',
                'İdxal və ixrac proseslərinin optimallaşdırılması.',
                'Logistika və daşınma planlaması.',
                'Risklərin azaldılması və xərclərin optimallaşdırılması.',
                'Tarif kodlarının düzgün müəyyənləşdirilməsi üzrə rəy.',
            ],
        ],
        [
            'h' => 'Nəticə',
            'p' => ['Bizimlə əməkdaşlıq edərək gömrük və logistika proseslərinizi daha sürətli və effektiv şəkildə idarə edə, təkrarlanan səhvlərin qarşısını ala bilərsiniz.'],
        ],
    ],
],

'konsiqnasiya-xidmetleri' => [
    'no'    => '07',
    'title' => 'Konsiqnasiya işlərin görülməsi',
    'short' => 'Konsiqnasiya anbarları, malların saxlanması və mərhələli rəsmiləşdirmə üzrə tam dəstək.',
    'img'   => 'services/konsiqnasiya-xidmetleri.jpg',
    'meta_title' => 'Konsiqnasiya xidmətləri Bakıda | Etibarlı və sürətli həllər',
    'meta_desc'  => 'Bakıda konsiqnasiya xidmətləri: malların saxlanması, idarə olunması, daşınması və hesabatların tərtibi.',
    'lead'  => 'Bakıda konsiqnasiya xidmətləri malların saxlanması, idarə olunması və satış prosesinin effektiv təşkili üçün vacibdir. Şirkətimiz konsiqnasiya əməliyyatlarını peşəkar şəkildə həyata keçirir.',
    'sections' => [
        [
            'h' => 'Konsiqnasiya işlərinin mərhələləri',
            'items' => [
                ['t' => 'Məhsulların toplanması',    'd' => 'Konsiqnasiya məhsulları tədarükçü tərəfindən hazırlanır və toplanır.'],
                ['t' => 'Daşıma və çatdırılma',      'd' => 'Məhsullar uyğun nəqliyyat vasitələri ilə alıcıya göndərilir.'],
                ['t' => 'Təhlükəsizlik və saxlama',  'd' => 'Məhsulların zərər görməməsi üçün saxlama və qoruma tədbirləri görülür.'],
                ['t' => 'Sifarişlərin idarə edilməsi','d' => 'Tədarükçü ilə alıcı arasında əlaqələr qurulur, sifarişlər vaxtında icra olunur.'],
                ['t' => 'Ödənişlər və hesabatlar',   'd' => 'Ödənişlər aparıldıqda və məhsullar çatdırıldıqda hesabatlar tərtib edilir.'],
            ],
        ],
        [
            'h' => 'Nə üçün vacibdir?',
            'p' => ['Konsiqnasiya işləri ticarət prosesində əhəmiyyətli rol oynayır. Düzgün idarə olunması malların müvafiq şəkildə çatdırılmasını və alıcıların tələblərinə cavab verilməsini təmin edir.'],
        ],
    ],
],

];

// ---------------------------------------------------------------- ÜSTÜNLÜKLƏR
$WHY_US = [
    ['t' => 'Peşəkar təcrübə',  'd' => '2018-ci ildən 18 fərqli sahədə praktiki iş təcrübəsi.'],
    ['t' => 'Fərdi yanaşma',    'd' => 'Hər müştəri üçün ayrıca menecer və fərdi həll.'],
    ['t' => 'Sürətli nəticə',   'd' => 'Sənədlərin operativ hazırlanması, minimum gecikmə.'],
    ['t' => 'Etibarlı xidmət',  'd' => 'Kommersiya məlumatlarının tam məxfiliyi.'],
];

// ---------------------------------------------------------------- XƏBƏRLƏR
$NEWS_IMG = 'Hansi-hallarda-qisa-idxal-beyannamesinde-duzelisler.jpg';

$NEWS = [

'dini-teyinatli-edebiyyata-icaze-senedi' => [
    'title' => 'Dini təyinatlı ədəbiyyata gömrük sərhədindən keçirilərkən hansı icazə sənədi tələb olunur?',
    'date'  => '2023-11-20',
    'video' => 'jhm22kzdjE8',
    'question' => 'Sadələşdirilmiş qaydada gömrük sərhədindən keçirilən və beynəlxalq poçt göndərişləri vasitəsilə alınan (göndərilən) dini təyinatlı ədəbiyyata Azərbaycan Respublikasının gömrük sərhədindən keçirilərkən hansı dövlət orqanı tərəfindən verilən icazə sənədi tələb olunur?',
    'excerpt' => 'Sadələşdirilmiş qaydada gömrük sərhədindən keçirilən və beynəlxalq poçt göndərişləri vasitəsilə alınan dini təyinatlı ədəbiyyat barədə rəsmi izah.',
],

'gomruk-borcu-kim-terefinden-odenile-biler' => [
    'title' => 'Gömrük borcu kim tərəfindən ödənilə bilər?',
    'date'  => '2023-11-14',
    'video' => '1V4WXnLFI9g',
    'question' => 'Gömrük borcu kim tərəfindən ödənilə bilər?',
    'excerpt' => 'Gömrük borcunun ödənilməsi üzrə öhdəlik daşıyan şəxslər barədə Dövlət Gömrük Komitəsinin izahı.',
],

'mallarin-rusum-dereceleri-haqqinda-melumat' => [
    'title' => 'Malların rüsum dərəcələri haqqında necə məlumat almaq olar?',
    'date'  => '2023-11-08',
    'video' => 'GItLXBKYxpQ',
    'question' => 'Malların rüsum dərəcələri haqqında necə məlumat almaq olar?',
    'excerpt' => 'İdxal-ixrac əməliyyatlarında tətbiq olunan rüsum dərəcələrinin öyrənilmə qaydası.',
],

'musayiet-olunmayan-baqajda-gomruk-beyannamesi' => [
    'title' => 'Müşayiət olunmayan baqajda gətirilən mallara bəyannamə hansı müddətdə verilməlidir?',
    'date'  => '2023-10-27',
    'video' => 'pmgL8DY_U98',
    'question' => 'Fiziki şəxslər tərəfindən müşayiət olunmayan baqajda gətirilən və aparılan mallara dair gömrük bəyannaməsi hansı müddətdə verilməlidir?',
    'excerpt' => 'Fiziki şəxslərin müşayiət olunmayan baqajı üzrə bəyannamə vermə müddətləri.',
],

'gomruk-borcu-hansi-qaydada-odenilir' => [
    'title' => 'Gömrük ödənişləri üzrə yaranmış gömrük borcu hansı qaydada ödənilir?',
    'date'  => '2023-10-18',
    'video' => 'leaXV62tAbs',
    'question' => 'Gömrük ödənişləri üzrə yaranmış gömrük borcu hansı qaydada ödənilir?',
    'excerpt' => 'Gömrük ödənişləri üzrə borcun ödənilmə qaydası və müddətləri barədə rəsmi izah.',
],

'gomruk-rusumlarinin-hesablanmasi' => [
    'title' => 'İdxal olunan mallara münasibətdə gömrük rüsumlarının hesablanması',
    'date'  => '2023-10-05',
    'video' => 'RmKUjzZsBsM',
    'question' => 'İdxal olunan mallara münasibətdə gömrük rüsumlarının və yığılması gömrük orqanlarına həvalə edilmiş vergilərin hesablanması zamanı hansı vaxt əsas götürülür?',
    'excerpt' => 'Gömrük rüsumları idxal olunan malların dəyərinə əsasən hesablanır və rəsmiləşdirmə prosesində vacib rol oynayır.',
],

];

/* ---------------------------------------------------------------
   Admin panelindən gələn məzmun (baza qurulubsa massivləri əvəz edir)
   --------------------------------------------------------------- */
require_once __DIR__ . '/content.php';
