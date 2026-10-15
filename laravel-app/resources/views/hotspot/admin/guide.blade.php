@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>MikroTik setup</h3>
    @include('hotspot.admin.nav')
    <p>Do these steps on the router yourself. This app never rewrites the firewall, NAT, or the hotspot server. The first router is the RB2011. The same users work for a later router because login is RADIUS, not a database stored only on one box.</p>

    <h4>1. Keep the API off the public internet</h4>
    <p>API (8728) and RADIUS UDP (1812/1813) listen on the tunnel address only. The phone never talks to the API. The captive portal talks to this website, then this website talks to the router through the tunnel.</p>
    <p>RouterOS 7 can use WireGuard. RouterOS 6 cannot. On RouterOS 6 use IPsec or L2TP and dial out to the VPS, because the shop WAN may be behind CGNAT. The VPS must not depend on connecting inbound to a private shop address.</p>
    <p>WireGuard on RouterOS 7, on the router:</p>
    <pre>/interface wireguard add name=wg-beyond listen-port=13231
/ip address add address=10.200.0.2/24 interface=wg-beyond
/interface wireguard peers add interface=wg-beyond public-key="VPS_PUBLIC_KEY" endpoint-address=VPS_PUBLIC_IP endpoint-port=13231 allowed-address=10.200.0.1/32 persistent-keepalive=25s
/ip firewall filter add chain=input in-interface=wg-beyond protocol=tcp dst-port=8728 action=accept place-before=0
/ip service set api address=10.200.0.0/24 disabled=no</pre>
    <p>IPsec or L2TP on RouterOS 6 uses the same idea: the router dials the VPS, gets a tunnel address such as 10.200.0.2, and the API address list contains only that tunnel. Save 10.200.0.2 in the router form above. Do not save a public IP.</p>

    <h4>2. Hotspot and walled garden</h4>
    <p>Create the hotspot server, DHCP, and NAT on the router once. Then set the login URL to <code>https://beyondtechworld.com/hotspot/portal</code>. Allow only these hosts before login. Everything else stays blocked. HTTPS internet is not intercepted; phones still reach the portal because captive detection uses plain HTTP.</p>
    <pre>/ip hotspot walled-garden ip add dst-host=beyondtechworld.com
/ip hotspot walled-garden ip add dst-host=www.campay.net
/ip hotspot walled-garden ip add dst-host=campay.net
/ip hotspot walled-garden ip add dst-host=api.campay.net
/ip hotspot walled-garden ip add dst-host=checkout.stripe.com
/ip hotspot walled-garden ip add dst-host=js.stripe.com
/ip hotspot walled-garden ip add dst-host=m.stripe.network
/ip hotspot walled-garden ip add dst-host=api.stripe.com</pre>
    <p>Missing a payment host here breaks MoMo or card checkout. The app will not add these rules for you.</p>

    <h4>3. RADIUS</h4>
    <p>Do not run User Manager on the RB2011. Point the hotspot at FreeRADIUS on the VPS, over the tunnel:</p>
    <pre>/radius add service=hotspot address=10.200.0.1 secret="SAME_SECRET_SAVED_HERE" authentication-port=1812 accounting-port=1813
/ip hotspot profile set default use-radius=yes</pre>
    <p>FreeRADIUS reads <code>radcheck</code> and <code>radreply</code> in this database. Attributes written for each user are Cleartext-Password, Mikrotik-Rate-Limit, Session-Timeout, Simultaneous-Use, and Mikrotik-Total-Limit when the package has a data cap. When the tunnel is up, the same user is also created under <code>/ip/hotspot/user</code> so a test login still works before FreeRADIUS is installed. A dead tunnel does not cancel a confirmed payment. Provisioning retries on the hotspot queue.</p>

    <h4>4. What staff do in this module</h4>
    <ul>
        <li>Save the location, the package price, and the router while it is still offline.</li>
        <li>Test the connection after the tunnel exists. Online means the API answered. Offline means it did not.</li>
        <li>Push a rate profile only when you mean to. That creates or updates <code>btw-{package id}</code> and nothing else.</li>
        <li>Sell with MoMo or Visa. Access turns on only after Campay or Stripe confirms the amount. A browser return of SUCCESSFUL does not.</li>
        <li>Print voucher codes for a test or a cash sale. Revoke makes that code unable to log in.</li>
    </ul>
    <p>Support WhatsApp on the portal: https://wa.me/{{ $support }}</p>
    <p>Run a separate queue worker for router retries so they do not wait behind WhatsApp:</p>
    <pre>php artisan queue:work database --queue=hotspot --sleep=3 --tries=12</pre>
    <p>Expiry runs from <code>php artisan hotspot:enforce</code> every minute. Until the tunnel exists, routers stay offline and access is not granted on the router. RADIUS rows are still written so the user is ready when the link returns.</p>
</div></section>
@endsection
