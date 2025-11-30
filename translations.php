<?php

$RASTRO_LANGUAGE_META = [
    'pt' => ['label' => 'Português', 'html_lang' => 'pt-BR'],
    'en' => ['label' => 'English', 'html_lang' => 'en'],
];

$RASTRO_TRANSLATIONS = [
    'pt' => [
        'app.title' => 'Rastro Timeline',
        'app.subtitle' => 'Visualização do histórico do Google Maps',
        'toggle.rawsignals' => 'Mostrar sinais brutos (rawSignals)',
        'action.import_json' => 'Importar JSON',
        'action.toggle_places' => 'Locais visitados',
        'action.logout' => 'Sair',
        'panel.language' => 'Idioma',
        'places.summary.title' => 'Locais visitados',
        'places.summary.note' => 'Fontes: Natural Earth / IBGE',
        'places.column.countries' => 'Países',
        'places.column.states' => 'Estados (BR)',
        'places.column.cities' => 'Cidades (BR)',
        'places.button.refresh' => 'Atualizar',
        'places.button.loading' => 'Aguarde...',
        'places.placeholder.loading' => 'Carregando...',
        'places.placeholder.generating' => 'Gerando lista...',
        'places.placeholder.click_refresh' => 'Clique em Atualizar para gerar.',
        'places.placeholder.error' => 'Erro ao carregar. Tente novamente.',
        'places.none.countries' => 'Nenhum país',
        'places.none.states' => 'Nenhum estado',
        'places.none.cities' => 'Nenhuma cidade',
        'places.residence' => 'Residência',
        'places.visit_singular' => '{{count}} visita',
        'places.visit_plural' => '{{count}} visitas',
        'places.error.load' => 'Erro ao carregar locais visitados',
        'import.modal.title' => 'Importar histórico do Google',
        'import.modal.description' => 'Selecione um arquivo JSON exportado pelo Serviços de localização no Android (Linha do Tempo) ou pelo Google Takeout (Location History / Semantic Location History).',
        'import.modal.start' => 'Começar importação',
        'import.modal.close' => 'Fechar',
        'import.choose_file_alert' => 'Escolha um arquivo JSON do Google Maps Timeline.',
        'import.reading_file' => 'Lendo arquivo "{{filename}}"...',
        'import.process_error' => 'Erro ao processar arquivo: {{message}}',
        'import.read_error' => 'Falha ao ler o arquivo.',
        'import.invalid_json' => 'Arquivo não é um JSON válido.',
        'import.unexpected_root' => 'JSON de formato inesperado (raiz não é um objeto).',
        'import.no_days' => 'Nenhum dia reconhecido no arquivo (formato não suportado?).',
        'import.analyzed' => 'Arquivo analisado. Dias detectados: {{count}}. Enviando para o servidor...',
        'import.success' => 'Importação concluída com sucesso. Dias importados: {{count}}.',
        'import.send_error' => 'Erro durante o envio ao servidor: {{message}}',
        'summary.distance' => 'Distância',
        'summary.moving_time' => 'Tempo em movimento',
        'summary.visits' => 'Visitas',
        'app.no_data_day' => 'Nenhum dado para este dia.',
        'app.error.days_list' => 'Erro ao carregar lista de dias: {{message}}',
        'app.error.load_day' => 'Erro ao carregar dia: {{message}}',
        'app.no_segments' => 'Sem segmentos para este dia.',
        'app.place' => 'Parada',
        'app.place_unknown' => 'Lugar',
        'units.kilometer' => 'km',
        'units.hour_short' => 'h',
        'units.minute_short' => 'min',
        'raw.kind.wifi' => 'Wi-Fi',
        'raw.kind.semantic_path' => 'Ponto de trajeto (semantic)',
        'raw.kind.position' => 'posição',
        'raw.precision' => 'Precisão: ~{{meters}} m',
        'raw.wifi_devices' => 'APs Wi-Fi: {{count}}',
        'mode.walking' => 'A pé',
        'mode.running' => 'Correndo',
        'mode.bicycle' => 'Bicicleta',
        'mode.car' => 'Carro',
        'mode.vehicle' => 'Em veículo',
        'mode.bus' => 'Ônibus',
        'mode.subway' => 'Metrô',
        'mode.train' => 'Trem',
        'mode.tram' => 'Bonde/VLT',
        'mode.ferry' => 'Balsa',
        'mode.flight' => 'Avião',
        'mode.trip_memory' => 'Memória de viagem',
        'mode.motorcycle' => 'Moto',
        'mode.taxi' => 'Táxi',
        'semantic.home' => 'Casa',
        'semantic.work' => 'Trabalho',
        'semantic.inferred_home' => 'Casa (inferido)',
        'semantic.inferred_work' => 'Trabalho (inferido)',
        'semantic.searched_address' => 'Endereço pesquisado',
        'auth.login.title' => 'Login • Rastro',
        'auth.login.heading' => 'Rastro • Login',
        'auth.login.username' => 'Usuário',
        'auth.login.password' => 'Senha',
        'auth.login.submit' => 'Entrar',
        'auth.login.hint.users' => 'Ajuste os usuários no arquivo .env.',
        'auth.login.hint.forgot' => 'Esqueceu sua senha?',
        'auth.login.error.invalid' => 'Usuário ou senha inválidos.',
        'auth.login.setup_warning' => 'Nenhum usuário configurado. Defina RASTRO_USERS_JSON no arquivo .env.',
        'auth.reset.notice.success' => 'Senha redefinida com sucesso. Entre novamente.',
        'auth.reset.notice.sent' => 'Se o e-mail informado estiver cadastrado, você receberá instruções em instantes.',
        'auth.forgot.title' => 'Recuperar senha • Rastro',
        'auth.forgot.heading' => 'Redefinir senha',
        'auth.forgot.description' => 'Informe o e-mail associado à sua conta. Vamos enviar um link para criar uma nova senha.',
        'auth.forgot.email' => 'E-mail',
        'auth.forgot.submit' => 'Enviar link',
        'auth.forgot.error.invalid_email' => 'Informe um e-mail válido.',
        'auth.back_to_login' => 'Voltar ao login',
        'auth.reset.title' => 'Definir nova senha • Rastro',
        'auth.reset.heading' => 'Definir nova senha',
        'auth.reset.info.invalid' => 'Este link não é válido. Solicite uma nova redefinição de senha.',
        'auth.reset.form.password' => 'Nova senha',
        'auth.reset.form.password_confirm' => 'Confirmar nova senha',
        'auth.reset.submit' => 'Atualizar senha',
        'auth.reset.error.no_token' => 'Link inválido. Solicite uma nova redefinição.',
        'auth.reset.error.expired' => 'Link expirado ou inválido. Solicite uma nova redefinição.',
        'auth.reset.error.password_length' => 'A nova senha deve ter pelo menos 8 caracteres.',
        'auth.reset.error.password_match' => 'As senhas não coincidem.',
        'auth.reset.error.token' => 'Token inválido.',
        'auth.reset.error.update' => 'Não foi possível atualizar a senha. Verifique permissões do arquivo .env.',
        'email.reset.subject' => 'Rastro Timeline - Redefinição de senha',
        'email.reset.body' => "Olá {{username}},\n\nRecebemos um pedido para redefinir sua senha no Rastro Timeline.\nClique no link abaixo para criar uma nova senha (válido por 1 hora):\n{{link}}\n\nSe você não solicitou esta ação, ignore este e-mail.\n",
        'install.title' => 'Instalação • Rastro Timeline',
        'install.heading' => 'Instalar Rastro Timeline',
        'install.description' => 'Preencha os dados para configurar o banco, o usuário administrador e as informações do aplicativo. O instalador criará o banco (se necessário) e gravará o arquivo {{env}}.',
        'install.success' => 'Instalação concluída!',
        'install.success.link' => 'Ir para o login',
        'install.button.submit' => 'Instalar',
        'install.field.db_host' => 'Host do banco',
        'install.field.db_name' => 'Nome do banco',
        'install.field.db_user' => 'Usuário do banco',
        'install.field.db_pass' => 'Senha do banco',
        'install.field.app_url' => 'URL do aplicativo',
        'install.field.mail_from' => 'Remetente dos e-mails',
        'install.field.admin_user' => 'Usuário administrador',
        'install.field.admin_email' => 'E-mail administrador',
        'install.field.admin_password' => 'Senha do administrador',
        'install.validation.db_host' => 'Informe o host do banco.',
        'install.validation.db_name' => 'Informe o nome do banco.',
        'install.validation.db_user' => 'Informe o usuário do banco.',
        'install.validation.app_url' => 'Informe uma URL válida para o aplicativo.',
        'install.validation.mail_from' => 'Informe o remetente dos e-mails.',
        'install.validation.admin_user' => 'Usuário administrador deve ter ao menos 3 caracteres (letras/números).',
        'install.validation.admin_email' => 'Informe um e-mail válido para o administrador.',
        'install.validation.admin_password' => 'A senha do administrador deve ter ao menos 8 caracteres.',
        'install.error.generic' => 'Não foi possível concluir a instalação.',
        'install.error.db_connection' => 'Erro ao conectar/criar o banco: {{message}}',
        'install.error.schema' => 'Erro ao preparar o schema: {{message}}',
        'install.error.env_write' => 'Erro ao gravar o arquivo .env: {{message}}',
        'install.error.schema_missing' => 'Arquivo .sql-install não encontrado.',
        'install.error.env_permission' => 'Sem permissão para escrever o arquivo .env.',
    ],
    'en' => [
        'app.title' => 'Rastro Timeline',
        'app.subtitle' => 'Google Maps history viewer',
        'toggle.rawsignals' => 'Show raw signals (rawSignals)',
        'action.import_json' => 'Import JSON',
        'action.toggle_places' => 'Visited places',
        'action.logout' => 'Sign out',
        'panel.language' => 'Language',
        'places.summary.title' => 'Visited places',
        'places.summary.note' => 'Sources: Natural Earth / IBGE',
        'places.column.countries' => 'Countries',
        'places.column.states' => 'States (BR)',
        'places.column.cities' => 'Cities (BR)',
        'places.button.refresh' => 'Refresh',
        'places.button.loading' => 'Please wait...',
        'places.placeholder.loading' => 'Loading...',
        'places.placeholder.generating' => 'Generating list...',
        'places.placeholder.click_refresh' => 'Click Refresh to generate.',
        'places.placeholder.error' => 'Failed to load. Try again.',
        'places.none.countries' => 'No countries',
        'places.none.states' => 'No states',
        'places.none.cities' => 'No cities',
        'places.residence' => 'Residence',
        'places.visit_singular' => '{{count}} visit',
        'places.visit_plural' => '{{count}} visits',
        'places.error.load' => 'Failed to load visited places',
        'import.modal.title' => 'Import Google history',
        'import.modal.description' => 'Select a JSON file exported by Android Location Services (Timeline) or by Google Takeout (Location History / Semantic Location History).',
        'import.modal.start' => 'Start import',
        'import.modal.close' => 'Close',
        'import.choose_file_alert' => 'Choose a JSON file from Google Maps Timeline.',
        'import.reading_file' => 'Reading file "{{filename}}"...',
        'import.process_error' => 'Failed to process the file: {{message}}',
        'import.read_error' => 'Could not read the file.',
        'import.invalid_json' => 'The file is not valid JSON.',
        'import.unexpected_root' => 'Unexpected JSON format (root is not an object).',
        'import.no_days' => 'No days detected in the file (unsupported format?).',
        'import.analyzed' => 'File analyzed. Detected days: {{count}}. Sending to the server...',
        'import.success' => 'Import completed successfully. Imported days: {{count}}.',
        'import.send_error' => 'Error while sending to the server: {{message}}',
        'summary.distance' => 'Distance',
        'summary.moving_time' => 'Moving time',
        'summary.visits' => 'Visits',
        'app.no_data_day' => 'No data for this day.',
        'app.error.days_list' => 'Failed to load days list: {{message}}',
        'app.error.load_day' => 'Failed to load day: {{message}}',
        'app.no_segments' => 'No segments for this day.',
        'app.place' => 'Stop',
        'app.place_unknown' => 'Place',
        'units.kilometer' => 'km',
        'units.hour_short' => 'h',
        'units.minute_short' => 'min',
        'raw.kind.wifi' => 'Wi-Fi',
        'raw.kind.semantic_path' => 'Semantic path point',
        'raw.kind.position' => 'position',
        'raw.precision' => 'Accuracy: ~{{meters}} m',
        'raw.wifi_devices' => 'Wi-Fi APs: {{count}}',
        'mode.walking' => 'On foot',
        'mode.running' => 'Running',
        'mode.bicycle' => 'Bicycle',
        'mode.car' => 'Car',
        'mode.vehicle' => 'In vehicle',
        'mode.bus' => 'Bus',
        'mode.subway' => 'Subway',
        'mode.train' => 'Train',
        'mode.tram' => 'Tram/Light rail',
        'mode.ferry' => 'Ferry',
        'mode.flight' => 'Plane',
        'mode.trip_memory' => 'Trip memory',
        'mode.motorcycle' => 'Motorcycle',
        'mode.taxi' => 'Taxi',
        'semantic.home' => 'Home',
        'semantic.work' => 'Work',
        'semantic.inferred_home' => 'Home (inferred)',
        'semantic.inferred_work' => 'Work (inferred)',
        'semantic.searched_address' => 'Searched address',
        'auth.login.title' => 'Login • Rastro',
        'auth.login.heading' => 'Rastro • Sign in',
        'auth.login.username' => 'Username',
        'auth.login.password' => 'Password',
        'auth.login.submit' => 'Sign in',
        'auth.login.hint.users' => 'Manage the users in the .env file.',
        'auth.login.hint.forgot' => 'Forgot your password?',
        'auth.login.error.invalid' => 'Invalid username or password.',
        'auth.login.setup_warning' => 'No users configured. Set RASTRO_USERS_JSON in the .env file.',
        'auth.reset.notice.success' => 'Password reset successfully. Please sign in again.',
        'auth.reset.notice.sent' => 'If the provided email exists, you will receive instructions shortly.',
        'auth.forgot.title' => 'Reset password • Rastro',
        'auth.forgot.heading' => 'Reset password',
        'auth.forgot.description' => 'Enter the email associated with your account. We will send a link to create a new password.',
        'auth.forgot.email' => 'Email',
        'auth.forgot.submit' => 'Send link',
        'auth.forgot.error.invalid_email' => 'Enter a valid email address.',
        'auth.back_to_login' => 'Back to login',
        'auth.reset.title' => 'Set new password • Rastro',
        'auth.reset.heading' => 'Set a new password',
        'auth.reset.info.invalid' => 'This link is not valid. Request a new password reset.',
        'auth.reset.form.password' => 'New password',
        'auth.reset.form.password_confirm' => 'Confirm new password',
        'auth.reset.submit' => 'Update password',
        'auth.reset.error.no_token' => 'Invalid link. Request a new reset.',
        'auth.reset.error.expired' => 'Expired or invalid link. Request a new reset.',
        'auth.reset.error.password_length' => 'The new password must have at least 8 characters.',
        'auth.reset.error.password_match' => 'Passwords do not match.',
        'auth.reset.error.token' => 'Invalid token.',
        'auth.reset.error.update' => 'Could not update the password. Check .env permissions.',
        'email.reset.subject' => 'Rastro Timeline - Password reset',
        'email.reset.body' => "Hello {{username}},\n\nWe received a request to reset your password on Rastro Timeline.\nClick the link below to create a new password (valid for 1 hour):\n{{link}}\n\nIf you did not request this action, just ignore this message.\n",
        'install.title' => 'Installation • Rastro Timeline',
        'install.heading' => 'Install Rastro Timeline',
        'install.description' => 'Fill out the data to configure the database, the admin user, and the app information. The installer will create the database when needed and write the {{env}} file.',
        'install.success' => 'Installation completed!',
        'install.success.link' => 'Go to login',
        'install.button.submit' => 'Install',
        'install.field.db_host' => 'Database host',
        'install.field.db_name' => 'Database name',
        'install.field.db_user' => 'Database user',
        'install.field.db_pass' => 'Database password',
        'install.field.app_url' => 'App URL',
        'install.field.mail_from' => 'Email sender',
        'install.field.admin_user' => 'Admin user',
        'install.field.admin_email' => 'Admin email',
        'install.field.admin_password' => 'Admin password',
        'install.validation.db_host' => 'Enter the database host.',
        'install.validation.db_name' => 'Enter the database name.',
        'install.validation.db_user' => 'Enter the database user.',
        'install.validation.app_url' => 'Enter a valid app URL.',
        'install.validation.mail_from' => 'Enter the email sender.',
        'install.validation.admin_user' => 'Admin user must have at least 3 characters (letters/numbers).',
        'install.validation.admin_email' => 'Enter a valid admin email.',
        'install.validation.admin_password' => 'The admin password must have at least 8 characters.',
        'install.error.generic' => 'Could not finish the installation.',
        'install.error.db_connection' => 'Failed to connect/create the database: {{message}}',
        'install.error.schema' => 'Failed to prepare the schema: {{message}}',
        'install.error.env_write' => 'Failed to write the .env file: {{message}}',
        'install.error.schema_missing' => '.sql-install file not found.',
        'install.error.env_permission' => 'No permission to write the .env file.',
    ],
];

$RASTRO_LANG = null;

function rastro_default_lang(): string {
    return 'pt';
}

function rastro_available_languages(): array {
    global $RASTRO_LANGUAGE_META;
    return $RASTRO_LANGUAGE_META;
}

function rastro_language_meta(?string $lang = null): array {
    $lang = $lang ?: rastro_lang();
    $meta = rastro_available_languages();
    return $meta[$lang] ?? $meta[rastro_default_lang()] ?? ['label' => 'Português', 'html_lang' => 'pt-BR'];
}

function rastro_detect_lang(): string {
    $available = array_keys(rastro_available_languages());
    $requested = $_SESSION['rastro_lang'] ?? ($_COOKIE['rastro_lang'] ?? '');
    if ($requested && in_array($requested, $available, true)) {
        return $requested;
    }

    $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    if ($accept) {
        $parts = explode(',', $accept);
        foreach ($parts as $candidate) {
            $candidate = strtolower(trim($candidate));
            if ($candidate === '') {
                continue;
            }
            $code = substr($candidate, 0, 2);
            if (in_array($code, $available, true)) {
                return $code;
            }
        }
    }

    return rastro_default_lang();
}

function rastro_lang(): string {
    global $RASTRO_LANG;
    if ($RASTRO_LANG) {
        return $RASTRO_LANG;
    }
    $lang = rastro_detect_lang();
    $_SESSION['rastro_lang'] = $lang;
    $RASTRO_LANG = $lang;
    return $lang;
}

function rastro_set_lang(string $lang): void {
    global $RASTRO_LANG;
    $langs = rastro_available_languages();
    if (!isset($langs[$lang])) {
        $lang = rastro_default_lang();
    }
    $RASTRO_LANG = $lang;
    $_SESSION['rastro_lang'] = $lang;
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $params = [
        'expires' => time() + 3600 * 24 * 365,
        'path' => '/',
        'secure' => $secure,
        'httponly' => false,
        'samesite' => 'Lax',
    ];
    setcookie('rastro_lang', $lang, $params);
}

function rastro_translations(): array {
    global $RASTRO_TRANSLATIONS;
    return $RASTRO_TRANSLATIONS;
}

function rastro_t(string $key, array $vars = [], ?string $lang = null): string {
    $lang = $lang ?: rastro_lang();
    $translations = rastro_translations();
    $fallback = rastro_default_lang();
    $value = $translations[$lang][$key] ?? $translations[$fallback][$key] ?? $key;
    if (!$vars) {
        return $value;
    }
    foreach ($vars as $name => $val) {
        $value = str_replace('{{' . $name . '}}', (string) $val, $value);
    }
    return $value;
}

function rastro_client_i18n_data(): array {
    return [
        'current' => rastro_lang(),
        'fallback' => rastro_default_lang(),
        'translations' => rastro_translations(),
        'languages' => rastro_available_languages(),
    ];
}

function rastro_html_lang(): string {
    $meta = rastro_language_meta();
    return $meta['html_lang'] ?? 'pt-BR';
}
