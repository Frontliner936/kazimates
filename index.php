<?php
require __DIR__ . '/includes/bootstrap.php';
if (uid()) { header('Location: dashboard.php'); exit; }
head('Home');
?>
<div class="km-home">
<section class="landing-hero">
  <div class="landing-copy">
    <span class="eyebrow"><span class="eyebrow-dot"></span> JOB SEEKER NETWORK</span>
    <h1>Connect with job seekers <em>near you.</em></h1>
    <p>Build a professional network by region, career interest and goals.</p>
    <div class="landing-actions"><a class="button button-large" href="register.php">Create account <span>→</span></a><a class="quiet-link" href="login.php">Log in</a></div>
    <div class="trust-line">Shared goals <span>·</span> Local connections <span>·</span> Peer support</div>
  </div>
  <div class="km-photo">
    <img src="assets/hero-community.jpg" alt="Three young job seekers smiling and working together around a laptop" width="1000" height="1000">
    <div class="visual-note note-top"><b>Local network</b><small>Members in your region</small></div>
    <div class="visual-note note-bottom"><span class="note-icon">↗</span><span><b>Matched by field</b><small>Career and skills</small></span></div>
  </div>
</section>
<section class="landing-values"><div class="value-heading"><span class="eyebrow">WHY KAZIMATES</span><h2>A network that <em>works for you.</em></h2></div><div class="value-grid"><article class="value-card"><span class="value-number">01</span><div class="value-icon">⌖</div><h3>Local</h3><p>Find job seekers in your region.</p></article><article class="value-card"><span class="value-number">02</span><div class="value-icon">✳</div><h3>Relevant</h3><p>Match by career interest, skills and education.</p></article><article class="value-card"><span class="value-number">03</span><div class="value-icon">↗</div><h3>Supportive</h3><p>Connect, message and share opportunities.</p></article></div></section>
<section class="km-band"><img src="assets/meetup.jpg" alt="Young graduates shaking hands at a networking meetup" loading="lazy" width="1300" height="739"><div class="km-band-copy"><span class="eyebrow">NETWORKING</span><h2>Opportunities start with people.</h2></div></section>
<section class="landing-cta"><div><h2>Ready to build your network?</h2></div><a class="button button-light button-large" href="register.php">Join KaziMates <span>→</span></a></section>
</div>
<?php foot(); ?>
