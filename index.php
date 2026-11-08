<?php
require __DIR__ . '/includes/bootstrap.php';
if (uid()) { header('Location: dashboard.php'); exit; }
head('Home');
?>
<div class="km-home">
<section class="landing-hero">
  <div class="landing-copy">
    <span class="eyebrow"><span class="km-symbol km-pin" aria-hidden="true"></span> JOB SEEKER NETWORK</span>
    <h1>Connect with job seekers <em>near you.</em></h1>
    <p>Build a professional network by region, career interest and goals.</p>
    <div class="landing-actions"><a class="button button-large" href="register.php">Create account <span aria-hidden="true">→</span></a><a class="quiet-link" href="login.php">Log in</a></div>
    <div class="trust-line">Shared goals <span>·</span> Local connections <span>·</span> Peer support</div>
  </div>
  <div class="km-photo">
    <img src="https://id-preview--01a2d745-9ab3-4384-9170-1e579de47efc.lovable.app/__l5e/assets-v1/982bfad9-1a4a-4c3e-9ef8-96f8de93c2c6/kazimates-community.jpg" alt="Four young professionals collaborating around a laptop" width="1536" height="1024" fetchpriority="high">
    <div class="visual-note note-top"><span class="km-location" aria-hidden="true"><span class="km-symbol km-pin"></span></span><span><b>Local network</b><small>Members in your region</small></span></div>
    <div class="visual-note note-bottom"><span class="km-symbol km-briefcase" aria-hidden="true"></span><span><b>Matched by field</b><small>Career and skills</small></span></div>
  </div>
</section>
<section class="landing-values"><div class="value-heading"><span class="eyebrow">WHY KAZIMATES</span><h2>A network that <em>works for you.</em></h2></div><div class="value-grid"><article class="value-card"><span class="value-number">01</span><div class="value-icon" aria-hidden="true"><span class="km-symbol km-pin"></span></div><h3>Local</h3><p>Find job seekers in your region.</p></article><article class="value-card"><span class="value-number">02</span><div class="value-icon" aria-hidden="true"><span class="km-symbol km-briefcase"></span></div><h3>Relevant</h3><p>Match by career interest, skills and education.</p></article><article class="value-card"><span class="value-number">03</span><div class="value-icon" aria-hidden="true"><span class="km-symbol km-message"></span></div><h3>Supportive</h3><p>Connect, message and share opportunities.</p></article></div></section>
<section class="km-band"><img src="https://id-preview--01a2d745-9ab3-4384-9170-1e579de47efc.lovable.app/__l5e/assets-v1/b8bb8a65-2de8-4b2a-9816-7d610f0542d0/kazimates-networking.jpg" alt="Young professionals making connections at an outdoor networking meetup" loading="lazy" width="1536" height="1024"><div class="km-band-copy"><span class="eyebrow"><span class="km-symbol km-message" aria-hidden="true"></span> NETWORKING</span><h2>Opportunities start with people.</h2></div></section>
<section class="landing-cta"><div><h2>Ready to build your network?</h2></div><a class="button button-light button-large" href="register.php">Join KaziMates <span aria-hidden="true">→</span></a></section>
</div>
<?php foot(); ?>
