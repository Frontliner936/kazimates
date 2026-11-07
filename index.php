<?php
require __DIR__ . '/includes/bootstrap.php';
if (uid()) { header('Location: dashboard.php'); exit; }
head('Find your people');
?>
<div class="km-home">
<section class="landing-hero">
  <div class="landing-copy">
    <span class="eyebrow"><span class="eyebrow-dot"></span> A COMMUNITY FOR THE JOURNEY</span>
    <h1>Your next connection could change <em>everything.</em></h1>
    <p>KaziMates brings job seekers together with people nearby who share their interests, qualifications and ambitions. Find your circle, exchange ideas and keep moving forward — together.</p>
    <div class="landing-actions"><a class="button button-large" href="register.php">Find your KaziMates <span>↗</span></a><a class="quiet-link" href="login.php">Already a member? Log in</a></div>
    <div class="trust-line"><span>✦</span> Shared goals <span>·</span> Local connections <span>·</span> Real peer support</div>
  </div>
  <div class="km-photo">
    <img src="assets/hero-community.jpg" alt="Three young job seekers smiling and working together around a laptop" width="1000" height="1000">
    <div class="visual-note note-top"><b>Better together</b><small>Meet people on your path</small></div>
    <div class="visual-note note-bottom"><span class="note-icon">↗</span><span><b>Find your circle</b><small>Start with what you share</small></span></div>
  </div>
</section>
<section class="landing-values"><div class="value-heading"><span class="eyebrow">WHY KAZIMATES</span><h2>Job searching is personal.<br><em>Your community should be, too.</em></h2></div><div class="value-grid"><article class="value-card"><span class="value-number">01</span><div class="value-icon">⌖</div><h3>People near you</h3><p>Discover fellow job seekers in your region and build connections rooted in your community.</p></article><article class="value-card"><span class="value-number">02</span><div class="value-icon">✳</div><h3>Common ground</h3><p>Meet people with similar career interests, skills, education and goals.</p></article><article class="value-card"><span class="value-number">03</span><div class="value-icon">↗</div><h3>Grow together</h3><p>Connect, exchange ideas, encourage one another and share useful opportunities.</p></article></div></section>
<section class="km-band"><img src="assets/meetup.jpg" alt="Young graduates shaking hands at a networking meetup" loading="lazy" width="1300" height="739"><div class="km-band-copy"><span class="eyebrow">REAL PEOPLE · REAL OPPORTUNITIES</span><h2>Every handshake is a door that could open.</h2></div></section>
<section class="landing-cta"><div><span class="eyebrow">YOUR PEOPLE ARE OUT THERE</span><h2>Make the journey<br>feel less like a solo one.</h2></div><a class="button button-light button-large" href="register.php">Join the community <span>↗</span></a></section>
</div>
<?php foot(); ?>
