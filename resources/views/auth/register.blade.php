<!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Create Account — Apparel Soft Track</title>
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
      <p class="auth-title">Create Account</p>
      <p class="auth-sub">Create your login. An admin has to approve the account before you can sign in.</p>

      @if ($errors->any())
        <div class="auth-error">{{ $errors->first() }}</div>
      @endif

      <form method="POST" action="{{ route('register') }}">
        @csrf
        <div class="field">
          <label for="name">Name</label>
          <input type="text" id="name" name="name" required autofocus value="{{ old('name') }}">
        </div>
        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required value="{{ old('email') }}">
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required>
        </div>
        <div class="field">
          <label for="password_confirmation">Confirm Password</label>
          <input type="password" id="password_confirmation" name="password_confirmation" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Create Account</button>
      </form>
      <p class="auth-footer">Already have an account? <a href="{{ route('login') }}">Log in</a></p>
    </div>
  </div>
</body>
</html>
