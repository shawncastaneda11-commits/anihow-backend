@extends('legal.layout', ['title' => $lang === 'fil' ? 'Patakaran sa Privacy' : 'Privacy Policy'])

{{-- Same text as the Privacy Policy tab in the app (privacyPolicySections). Keep both in step. --}}
@php
    $sections = $lang === 'fil' ? [
        ['Tungkol sa patakarang ito', 'Ipinapaliwanag nito kung anong personal na impormasyon ang kinokolekta ng AniHow, bakit, at paano ito pinoprotektahan. Sinusunod namin ang Data Privacy Act of 2012 (Republic Act No. 10173).'],
        ['Ano ang kinokolekta namin', 'Account: pangalan, email, phone (opsyonal), password (naka-encrypt, hindi namin nababasa), at profile photo (opsyonal). Pag-verify ng ID (buyer): larawan ng iyong government ID at, kung idinagdag mo, selfie na hawak ito. Gamit: mga order, reserbasyon, review, paborito, at chat sa seller. Bayad: ang screenshot o patunay ng online na bayad na ina-upload mo; hindi namin nakikita ang password ng card o e-wallet mo. Seller: shop profile, listing at larawan nito, at tala ng ani at benta. Device: notification token kung pinayagan mo ang abiso.'],
        ['Lokasyon', 'Kapag pinindot mo lang ang Near me, ipinapadala nang isang beses ang lokasyon ng phone mo para ayusin ang mga farm ayon sa layo. Hindi ito iniimbak.'],
        ['Bakit namin ito ginagamit', 'Para patakbuhin ang mga order, reserbasyon at chat; magpadala ng verification at reset code sa email; magpadala ng abisong binuksan mo; panatilihing ligtas ang palengke (report at pagsuspinde); at ipakita sa seller ang buod ng benta nila.'],
        ['Sino ang nakakakita', 'Nakikita ng seller ang pangalan, phone at mensahe mo para sa iyong mga order. Nakikita ng buyer ang pangalan ng shop, profile at listing ng seller. Nakikita ng admin ang kailangan lang para suportahan ang app, suriin ang ID, at asikasuhin ang report. Hindi namin ibinebenta o pinauupahan ang data mo, at walang ads.'],
        ['Gaano katagal itinatago', 'Habang aktibo ang account mo. Binubura ang larawan ng ID sa sandaling masuri ito ng admin; ang resulta lang (beripikado o hindi) ang itinatago. Kapag binura ang account mo, aalisin ang pangalan, email, phone at mga larawan mo; mananatili ang mga order nang wala ang mga detalye mo para tama ang tala ng benta ng farm.'],
        ['Ang mga karapatan mo', 'Puwede mong tingnan at itama ang profile mo, mag-download ng kopya ng data mo, buksan o patayin ang abiso, at humiling na burahin ang account mo, lahat mula sa Settings. Puwede ka ring magreklamo sa National Privacy Commission.'],
        ['Pag-iingat sa data', 'Naka-encrypt ang password at ang koneksyon (HTTPS). Ang may access lang sa data ay ang mga taong kailangan ito sa trabaho nila.'],
        ['Mga tanong at pagbabago', 'Para sa tanong tungkol sa privacy, gamitin ang Help sa app o mag-email sa '.$contact.'. Kapag nagbago ang patakarang ito, hihilingin ng app na basahin at tanggapin mo ang bagong bersyon.'],
    ] : [
        ['About this policy', 'This explains what personal information AniHow collects, why, and how it is protected. We follow the Data Privacy Act of 2012 (Republic Act No. 10173).'],
        ['What we collect', 'Account: name, email, phone (optional), password (stored scrambled, we cannot read it), and a profile photo (optional). ID check (buyers): a photo of your government ID and, if you add one, a selfie holding it. Activity: orders, reservations, reviews, favorites, and chats with sellers. Payments: the screenshot or proof of an online payment that you upload; we never see your card or e-wallet password. Sellers: shop profile, listings and their photos, and harvest and sales records. Device: a notification token if you allow notifications.'],
        ['Location', 'Only when you tap Near me, your phone\'s location is sent once to sort farms by distance. It is not saved.'],
        ['Why we use it', 'To run orders, reservations and chats; send verification and reset codes by email; send the notifications you turn on; keep the marketplace safe (reports and suspensions); and show sellers their sales summaries.'],
        ['Who can see it', 'Sellers see your name, phone and messages for your orders. Buyers see a seller\'s shop name, profile and listings. Admins see only what they need to support the app, check IDs and handle reports. We do not sell or rent your data, and there are no ads.'],
        ['How long we keep it', 'While your account is active. ID photos are deleted as soon as an admin reviews them; only the result (verified or not) is kept. If your account is deleted, your name, email, phone and photos are removed; orders stay without your details so the farm\'s sales records remain correct.'],
        ['Your rights', 'You can view and correct your profile, download a copy of your data, turn notifications on or off, and ask to delete your account, all from Settings. You can also file a complaint with the National Privacy Commission.'],
        ['Keeping it safe', 'Passwords and the connection (HTTPS) are encrypted. Only the people who need the data for their work can access it.'],
        ['Questions and changes', 'For privacy questions, use Help in the app or email '.$contact.'. When this policy changes, the app asks you to read and accept the new version.'],
    ];
@endphp

@section('content')
    @foreach ($sections as [$heading, $body])
        <h2>{{ $heading }}</h2>
        <p>{{ $body }}</p>
    @endforeach
@endsection
