@extends('legal.layout', ['title' => $lang === 'fil' ? 'Burahin ang iyong AniHow account' : 'Delete your AniHow account'])

@section('content')
    @if ($lang === 'fil')
        <p>Puwede mong burahin ang account na ginagamit mo sa AniHow, ang market hub app para sa mga magsasaka sa Cavite.</p>
        <h2>Sa app</h2>
        <ol>
            <li>Buksan ang AniHow at mag-sign in.</li>
            <li>Pindutin ang iyong larawan sa itaas, pagkatapos ang <strong>Settings</strong>.</li>
            <li>Pindutin ang <strong>My data</strong>.</li>
            <li>Sa <strong>Burahin ang account ko</strong>, pindutin ang <strong>Request account deletion</strong>, pagkatapos ang <strong>Send request</strong>.</li>
        </ol>
        <p>Susuriin ng administrator ang kahilingan. Puwede mo itong kanselahin habang hinihintay pa. Hindi ka puwedeng humiling habang may order kang hindi pa tapos (placed, confirmed o ready).</p>
        <h2>Kung hindi mo mabuksan ang app</h2>
        <p>Mag-email sa <a href="mailto:{{ $contact }}?subject=Delete%20my%20AniHow%20account">{{ $contact }}</a> mula sa email ng account mo, na may paksang "Delete my AniHow account".</p>
        <h2>Ano ang binubura at ano ang naiiwan</h2>
        <ul>
            <li><strong>Binubura:</strong> ang pangalan, email, phone at mga larawan mo, ang cart, mga paborito at sinusundang bukid, mga abiso, at ang pag-sign in mo.</li>
            <li><strong>Naiiwan:</strong> ang mga natapos na order, na makikita bilang "Deleted user" at wala na ang mga detalye mo, para tama ang tala ng benta ng bukid.</li>
        </ul>
    @else
        <p>You can delete the account you use in AniHow, the market hub app for farmers in Cavite.</p>
        <h2>In the app</h2>
        <ol>
            <li>Open AniHow and sign in.</li>
            <li>Tap your profile picture at the top, then <strong>Settings</strong>.</li>
            <li>Tap <strong>My data</strong>.</li>
            <li>Under <strong>Delete my account</strong>, tap <strong>Request account deletion</strong>, then <strong>Send request</strong>.</li>
        </ol>
        <p>An administrator reviews the request. You can cancel it while it is waiting. You can't send one while you still have an order in progress (placed, confirmed or ready).</p>
        <h2>If you can't open the app</h2>
        <p>Email <a href="mailto:{{ $contact }}?subject=Delete%20my%20AniHow%20account">{{ $contact }}</a> from the email on your account, with the subject "Delete my AniHow account".</p>
        <h2>What is deleted and what is kept</h2>
        <ul>
            <li><strong>Deleted:</strong> your name, email, phone and photos, your cart, favorites and followed farms, your notifications, and your sign-in.</li>
            <li><strong>Kept:</strong> completed orders, shown as "Deleted user" without your details, so the farm's sales records stay correct.</li>
        </ul>
    @endif
@endsection
