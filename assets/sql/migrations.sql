
CREATE TABLE `caixas_peca` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `empresa_cnpj` varchar(20) NOT NULL,
  `tipo` enum('individual','compartilhado') NOT NULL,
  `terminal` varchar(60) DEFAULT NULL,
  `aberto_por_cpf` varchar(20) DEFAULT NULL,
  `aberto_em` datetime NOT NULL DEFAULT current_timestamp(),
  `saldo_inicial` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('aberto','fechado') NOT NULL DEFAULT 'aberto',
  `fechado_por_cpf` varchar(20) DEFAULT NULL,
  `fechado_em` datetime DEFAULT NULL,
  `observacoes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `caixa_movimentos_peca`
--

CREATE TABLE `caixa_movimentos_peca` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `empresa_cnpj` varchar(20) NOT NULL,
  `caixa_id` bigint(20) UNSIGNED NOT NULL,
  `tipo` enum('entrada','saida') NOT NULL,
  `forma_pagamento` varchar(40) DEFAULT NULL,
  `valor` decimal(12,2) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `caixa_participantes_peca`
--

CREATE TABLE `caixa_participantes_peca` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `caixa_id` bigint(20) UNSIGNED NOT NULL,
  `empresa_cnpj` varchar(20) NOT NULL,
  `operador_cpf` varchar(20) NOT NULL,
  `operador_nome` varchar(150) DEFAULT NULL,
  `entrou_em` datetime NOT NULL DEFAULT current_timestamp(),
  `saiu_em` datetime DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `categorias_lavagem_peca`
--

CREATE TABLE `categorias_lavagem_peca` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `empresa_cnpj` varchar(20) NOT NULL,
  `nome` varchar(120) NOT NULL,
  `descricao` text DEFAULT NULL,
  `valor_padrao` decimal(12,2) DEFAULT 0.00,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `categorias_produto_peca` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `empresa_cnpj` varchar(20) NOT NULL,
  `nome` varchar(120) NOT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `empresas_peca` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `cnpj` varchar(20) NOT NULL,
  `nome_fantasia` varchar(150) NOT NULL,
  `razao_social` varchar(200) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `endereco` text DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `estado` char(2) DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `status` enum('ativa','inativa','suspensa') NOT NULL DEFAULT 'ativa',
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `fornecedores_peca` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `empresa_cnpj` varchar(20) NOT NULL,
  `nome` varchar(180) NOT NULL,
  `cnpj_cpf` varchar(20) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `endereco` text DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `estado` char(2) DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `obs` text DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `lavadores_peca` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `empresa_cnpj` varchar(20) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `cpf` varchar(20) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `lavagens_peca` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `empresa_cnpj` varchar(20) NOT NULL,
  `lavador_cpf` varchar(20) NOT NULL,
  `placa` varchar(10) DEFAULT NULL,
  `modelo` varchar(120) DEFAULT NULL,
  `cor` varchar(30) DEFAULT NULL,
  `categoria_id` bigint(20) UNSIGNED DEFAULT NULL,
  `categoria_nome` varchar(120) DEFAULT NULL,
  `valor` decimal(12,2) NOT NULL,
  `forma_pagamento` varchar(40) DEFAULT 'dinheiro',
  `status` enum('aberta','concluida','cancelada') NOT NULL DEFAULT 'aberta',
  `checkin_at` datetime DEFAULT NULL,
  `checkout_at` datetime DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `mov_estoque_peca` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `empresa_cnpj` varchar(20) NOT NULL,
  `produto_id` bigint(20) UNSIGNED NOT NULL,
  `tipo` enum('entrada','saida','ajuste') NOT NULL,
  `qtd` decimal(12,3) NOT NULL,
  `origem` enum('compra','venda','ajuste','os') NOT NULL,
  `ref_id` bigint(20) UNSIGNED DEFAULT NULL,
  `usuario_cpf` varchar(20) DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `orcamentos_peca` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `empresa_cnpj` varchar(20) NOT NULL,
  `numero` int(10) UNSIGNED NOT NULL,
  `cliente_nome` varchar(150) DEFAULT NULL,
  `cliente_telefone` varchar(20) DEFAULT NULL,
  `cliente_email` varchar(150) DEFAULT NULL,
  `validade` date DEFAULT NULL,
  `status` enum('aberto','aprovado','rejeitado','expirado') NOT NULL DEFAULT 'aberto',
  `observacoes` text DEFAULT NULL,
  `total_bruto` decimal(12,2) NOT NULL DEFAULT 0.00,
  `desconto` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_liquido` decimal(12,2) NOT NULL DEFAULT 0.00,
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `orcamento_itens_peca` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `orcamento_id` bigint(20) UNSIGNED NOT NULL,
  `item_tipo` enum('produto','servico') NOT NULL,
  `item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `descricao` varchar(255) NOT NULL,
  `qtd` decimal(12,3) NOT NULL DEFAULT 1.000,
  `valor_unit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `valor_total` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `produtos_peca` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `empresa_cnpj` varchar(20) NOT NULL,
  `categoria_id` bigint(20) UNSIGNED DEFAULT NULL,
  `nome` varchar(180) NOT NULL,
  `sku` varchar(60) DEFAULT NULL,
  `ean` varchar(20) DEFAULT NULL,
  `marca` varchar(80) DEFAULT NULL,
  `unidade` varchar(10) DEFAULT 'UN',
  `preco_custo` decimal(12,2) DEFAULT 0.00,
  `preco_venda` decimal(12,2) NOT NULL,
  `estoque_atual` decimal(12,3) NOT NULL DEFAULT 0.000,
  `estoque_minimo` decimal(12,3) NOT NULL DEFAULT 0.000,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `solicitacoes_empresas_peca` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nome_fantasia` varchar(150) NOT NULL,
  `cnpj` varchar(20) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `proprietario_nome` varchar(150) NOT NULL,
  `proprietario_email` varchar(150) NOT NULL,
  `proprietario_senha_hash` varchar(255) DEFAULT NULL,
  `status` enum('pendente','aprovada','recusada') NOT NULL DEFAULT 'pendente',
  `token_aprovacao` varchar(64) DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `solicitacoes_empresas_peca` (`id`, `nome_fantasia`, `cnpj`, `telefone`, `email`, `proprietario_nome`, `proprietario_email`, `proprietario_senha_hash`, `status`, `token_aprovacao`, `criado_em`) VALUES
(4, 'Junior', '12345678901234', NULL, NULL, 'Junior', 'lucasscorrea396@gmail.com', '$2y$10$7E9MTj1HjUaoMWY5b6zQQ.jzP3pltHd9k.5LxXy7bd0ACP0AwUJiu', 'aprovada', NULL, '2025-08-24 19:24:29');


CREATE TABLE `usuarios_peca` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `empresa_cnpj` varchar(20) DEFAULT NULL,
  `nome` varchar(120) NOT NULL,
  `email` varchar(150) NOT NULL,
  `cpf` varchar(20) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `senha` varchar(255) NOT NULL,
  `perfil` enum('super_admin','dono','funcionario') NOT NULL DEFAULT 'funcionario',
  `tipo_funcionario` enum('lavajato','autopeca','administrativo','caixa','estoque') DEFAULT 'lavajato',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `precisa_redefinir_senha` tinyint(1) NOT NULL DEFAULT 0,
  `senha_atualizada_em` datetime DEFAULT NULL,
  `ultimo_login_em` datetime DEFAULT NULL,
  `falhas_login` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ;


CREATE TABLE `usuarios_redefinicao_senha_peca` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `usuario_id` bigint(20) UNSIGNED DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `token` varchar(64) NOT NULL,
  `otp` varchar(6) DEFAULT NULL,
  `expiracao` datetime NOT NULL,
  `usado_em` datetime DEFAULT NULL,
  `ip_solicitante` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `vendas_peca` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `empresa_cnpj` varchar(20) NOT NULL,
  `vendedor_cpf` varchar(20) NOT NULL,
  `origem` enum('balcao','lavajato','orcamento') NOT NULL DEFAULT 'balcao',
  `status` enum('aberta','fechada','cancelada') NOT NULL DEFAULT 'fechada',
  `total_bruto` decimal(12,2) NOT NULL DEFAULT 0.00,
  `desconto` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_liquido` decimal(12,2) NOT NULL DEFAULT 0.00,
  `forma_pagamento` varchar(40) DEFAULT 'dinheiro',
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `venda_itens_peca`
--

CREATE TABLE `venda_itens_peca` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `venda_id` bigint(20) UNSIGNED NOT NULL,
  `item_tipo` enum('produto','servico') NOT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `qtd` decimal(12,3) NOT NULL DEFAULT 1.000,
  `valor_unit` decimal(12,2) NOT NULL,
  `valor_total` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

