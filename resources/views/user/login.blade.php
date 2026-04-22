
<html>

<head>

    <title>Login</title>
   
</head>

<body>
    <header>
    



    </header>


    <div class="container">
        <div class="left">
            <h1>Bem-Vindo<br>De Volta</h1>
            <p>Ainda não possui uma conta?</p>
            <a href="{{ route('user.cadastro') }}" class="cadastrar" id="btn-cadastrar">Cadastrar</a>
        </div>

        <div class="right">

            <h2>Login</h2>

            <form class="form" action="{{ route('login-user') }}" method="POST">
                @csrf
                @method('POST')

                <!-- E-mail -->

                <label for="email">E-mail</label>

                <input type="email" name="email" class="email @error('email') is-invalid @enderror" id="email" placeholder="Insira seu e-mail" value="{{ old('email') }}">

                @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror


                <!-- Senha -->

                <label for="password">Senha</label>

                <input type="password" name="password" class="senha @error('password') is-invalid @enderror" id="password" placeholder="Digite sua senha">
                @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror


                <!-- Botão de envio -->

                <button type="submit" class="logar">Entrar</button>


           



            </form>
        </div>
    </div>


    <script>
        document.getElementById('btn-cadastrar').addEventListener('click', function(event) {
            event.preventDefault(); // cancela o comportamento padrão do link

            document.body.style.transition = "opacity 0.5s";
            document.body.style.opacity = 0;

            setTimeout(() => {
                window.location.href = this.href; // navega para login após animação
            }, 500); // tempo da animação (500ms)
        });
    </script>


</body>

</html>