<p>Hi {{ $code->user->first_name }} {{ $code->user->last_name }},</p>

<div style="text-align: center">
  <h3>This is your forgot password code</h3>

  <h2>{{ $code->code }}</h2>
</div>