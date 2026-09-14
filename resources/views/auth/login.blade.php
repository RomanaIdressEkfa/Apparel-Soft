<!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Log In — Apparel Soft Track</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
  <div class="auth-shell">
    <div class="auth-card">
      <div class="auth-brand">
        <div class="brand-mark">
          <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v3H4z"/><path d="M6 7v13h12V7"/><path d="M9 11h6"/><path d="M9 15h6"/></svg>
        </div>
        <p class="brand">Apparel Soft Track</p>
      </div>
      <p class="auth-title">Log In</p>
      <p class="auth-sub">Sign in to view and update your work.</p>

      @if (session('status'))
        <div class="auth-notice">{{ session('status') }}</div>
      @endif

      @if ($errors->any())
        <div class="auth-error">{{ $errors->first() }}</div>
      @endif

      <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required autofocus value="{{ old('email') }}">
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Log In</button>
      </form>
      <p class="auth-footer">No account? <a href="{{ route('register') }}">Create one</a></p>
    </div>
  </div>
</body>
</html>
