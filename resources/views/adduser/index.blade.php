@extends('layouts.legacy')

@section('title', "Add user")

@section('content')
<h1>Add user</h1>
<form method=post action=adduser.php>
<div class="nx-fgrid">
<div class="nx-fhead">User name</div><div class="nx-fcell"><input type=text name=username size=40></div>
<div class="nx-fhead">Password</div><div class="nx-fcell"><input type=password name=password size=40></div>
<div class="nx-fhead">Re-type password</div><div class="nx-fcell"><input type=password name=password2 size=40></div>
<div class="nx-fhead">E-mail</div><div class="nx-fcell"><input type=text name=email size=40></div>
<div class="nx-ffull nx-center"><input type=submit value="Okay" class=btn></div>
</div>
</form>
@endsection
