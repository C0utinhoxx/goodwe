<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            font-family: Arial, sans-serif;
            color: white;
        }

        .container-login {
            display: flex;
            min-height: 100vh;
            background-color: #0f1117;
        }

        .lado-visual {
            width: 60%;
            background-color: #1a1a1a;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            background-image: url('/imagens/login-goodwe.png');
            background-size: cover;
            background-position: 20% center;
            background-repeat: no-repeat;
            overflow: hidden;
        }

        .lado-visual::before {
            content: "";
            position: absolute;
            inset: 0;
            background-color: rgba(0, 0, 0, 0.20);
        }

        .conteudo-visual {
            width: 70%;
            max-width: 620px;
            position: absolute;
            left: 35px;
            top: 15%;
        }

        .conteudo-visual h1 {
            font-size: 36px;
            line-height: 1.2;
            margin-bottom: 15px;
        }

        .conteudo-visual p {
            font-size: 16px;
            line-height: 1.6;
            color: #c7c7c7;
            max-width: 480px;
        }

        .logo-goodwe {
            position: absolute;
            top: 30px;
            left: 35px;
            color: #e53935;
            font-size: 28px;
            font-weight: bold;
        }

        .linha-visual {
            width: 45px;
            height: 3px;
            background-color: #e53935;
            margin: 18px 0 20px;
        }

        .beneficios {
            position: absolute;
            bottom: 55px;
            left: 35px;
            display: flex;
            gap: 30px;
        }

        .beneficio-card {
            background-color: rgba(15, 17, 23, 0.45);
            border-radius: 8px;
            padding: 12px 16px;
        }

        .beneficio-card span {
            color: white;
            font-size: 14px;
            font-weight: bold;
        }

        .logo-goodwe,
        .conteudo-visual,
        .beneficios {
            z-index: 1;
        }

        .lado-formulario {
            width: calc(40% + 28px);
            margin-left: -28px;
            background-color: #0f1117;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            border-radius: 28px 0 0 28px;
            z-index: 2;
        }

        .conteudo-formulario {
            width: 85%;
            max-width: 460px;
        }

        .conteudo-formulario h2 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .conteudo-formulario p {
            color: #a0a0a0;
            margin-bottom: 30px;
        }

        .conteudo-formulario h3 {
            font-size: 18px;
            margin-bottom: 8px;
            font-weight: normal;
        }

        .form {
            display: flex;
            flex-direction: column;
        }

        .form > label {
            font-size: 14px;
            margin-bottom: 10px;
        }

        .email, 
        .senha {
            width: 100%;
            padding: 14px;
            margin-bottom: 24px;
            border: 1px solid #343843;
            border-radius: 6px;
            background-color: #181b22;
            color: white;
        }

        .email::placeholder,
        .senha::placeholder {
            color: #8a8f98;
        }

        .email:focus,
        .senha:focus {
            outline: none;
            border-color: #e53935;
        }

        .invalid-feedback {
            color: #ff5a55;
            font-size: 12px;
            margin-top: -18px;
            margin-bottom: 18px;
        }

        .is-invalid {
            border-color: #e53935;
        }

        .opcoes-login {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .opcoes-login a {
            color: #e53935;
            text-decoration: none;
        }

        .opcoes-login label {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .opcoes-login input[type="checkbox"] {
            appearance: none;
            width: 16px;
            height: 16px;
            border: 1px solid #555b66;
            border-radius: 3px;
            background-color: #181b22;
            cursor: pointer;
            position: relative;
        }

        .opcoes-login input[type="checkbox"]:checked {
            background-color: #e53935;
            border-color: #e53935;
        }

        .opcoes-login input[type="checkbox"]:checked::after {
            content: "✓";
            position: absolute;
            color: white;
            font-size: 12px;
            font-weight: bold;
            left: 2px;
            top: -1px;
        }

        .logar {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 8px;
            background-color: #e53935;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        .logar:hover {
            background-color: #c62828;
        }

        .texto-cadastro {
            text-align: center;
            margin-top: 25px;
            font-size: 14px;
            color: #a0a0a0;
        }

        .texto-cadastro a {
            color: #e53935;
            text-decoration: none;
            font-weight: bold;
        }

        .linha-vermelha {
            width: 45px;
            height: 3px;
            background-color: #e53935;
            margin: 12px 0 20px;
        }

        .rodape-login {
            position: absolute;
            bottom: 25px;
            left: 50%;
            transform: translateX(-50%);
            width: 85%;
            text-align: center;
            color: #8a8f98;
            font-size: 12px;
        }

        .rodape-login p {
            margin-bottom: 8px;
            font-size: 14px;
            color: #b0b4bc;
        }

        .idioma {
                position: absolute;
                top: 25px;
                right: 30px;
                z-index: 10;
        }

        .botao-idioma {
                background-color: #2a2d34;
                color: white;
                border: none;
                border-radius: 8px;
                padding: 8px 12px;
                cursor: pointer;
                font-size: 14px;
        }

        .menu-idioma {
            display: none;
            position: absolute;
            top: 42px;
            right: 0;
            width: 120px;
            background-color: #2a2d34;
            border-radius: 8px;
            padding: 8px 0;
        }

        .menu-idioma a {
            display: block;
            padding: 8px 12px;
            color: white;
            text-decoration: none;
            font-size: 14px;
        }

        .menu-idioma a:hover {
            background-color: #3a3d45;
        }

        .idioma:hover .menu-idioma {
            display: block;
        }

        @media (max-width: 768px) {

            .lado-visual {
                display: none;
            }

            .lado-formulario {
                width: 100%;
                min-height: 100vh;
                margin-left: 0;
                border-radius: 0;
            }

            .conteudo-formulario {
                width: 85%;
                min-height: 460px;
            }

            .rodape-login {
                bottom: 20px;
            }
        }
    </style>

    <link rel="icon" href="{{ asset('imagens/logo.png') }}" type="image/x-icon">
</head>

<body>
    
    <div class="container-login">
        <div class="lado-visual">
            <div class="logo-goodwe">
                GOODWE
            </div>

            <div class="conteudo-visual">
                <h1>Energia inteligente para uma mobilidade mais sustentável.</h1>

                <div class="linha-visual"></div>

                <p>
                    Gestão inteligente de recargas, mais eficiência para hoje
                    e mais sustentabilidade para o amanhã.
                </p>
            </div>

            <div class="beneficios">
                <div class="beneficio-card">
                    <span>Recarga inteligente</span>
                </div>
                <div class="beneficio-card">
                    <span>Gestão de energia</span>
                </div>
                <div class="beneficio-card">
                    <span>Sustentabilidade em cada escolha</span>   
                </div>
            </div>
        </div>

        <div class="lado-formulario">
            <div class="idioma">
                <button type="button" class="botao-idioma">
                    Português
                </button>
                <div class="menu-idioma">
                    <a href="#">Português</a>
                    <a href="#">English</a>
                    <a href="#">Español</a>
                </div>
            </div>

            <div class="conteudo-formulario">

                <h2>Bem-vindo!</h2>

                <div class="linha-vermelha"></div>

                <h3>Plataforma de Gestão de Recargas</h3>

                <p>
                    Acesse sua conta para continuar.
                </p>

                <form class="form" action="{{ route('login-user') }}" method="POST">
                    @csrf
                    @method('POST')

                    <label for="email">E-mail</label>

                    <input type="email" name="email" class="email @error('email') is-invalid @enderror" id="email" placeholder="Insira seu e-mail" value="{{ old('email') }}">

                    @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror

                    <label for="password">Senha</label>

                    <input type="password" name="password" class="senha @error('password') is-invalid @enderror" id="password" placeholder="Digite sua senha">
                    @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror

                    <div class="opcoes-login">
                        <label>
                            <input type="checkbox" name="lembrar">
                            Lembrar de mim
                        </label>

                        <a href="#">Esqueci minha senha</a>
                    </div>

                    <button type="submit" class="logar">Entrar</button>
                </form>

                <p class="texto-cadastro">
                    Não possui uma conta?
                    <a href="{{ route('user.cadastro') }}">Cadastre-se</a>
                </p>
            </div>

            <div class="rodape-login">
                <p>Smart Energy for a Better Future</p>
                <span>EQUIPE2 1CCPX GOODWE © 2026. Todos os direitos reservados.</span>
            </div>
        </div>
    </div>
</body>

</html>