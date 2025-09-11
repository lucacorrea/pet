CREATE TABLE caixas_peca (
  id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  empresa_cnpj VARCHAR(20) NOT NULL,
  tipo ENUM('individual','compartilhado') NOT NULL,
  terminal VARCHAR(60) DEFAULT NULL,
  aberto_por_cpf VARCHAR(20) DEFAULT NULL,
  aberto_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  saldo_inicial DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  status ENUM('aberto','fechado') NOT NULL DEFAULT 'aberto',
  fechado_por_cpf VARCHAR(20) DEFAULT NULL,
  fechado_em DATETIME DEFAULT NULL,
  observacoes TEXT
);

CREATE TABLE caixa_movimentos_peca (
  id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  empresa_cnpj VARCHAR(20) NOT NULL,
  caixa_id INT NOT NULL,
  tipo ENUM('entrada','saida') NOT NULL,
  forma_pagamento VARCHAR(40) DEFAULT NULL,
  valor DECIMAL(12,2) NOT NULL,
  descricao VARCHAR(255) DEFAULT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE caixa_participantes_peca (
  id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  caixa_id INT NOT NULL,
  empresa_cnpj VARCHAR(20) NOT NULL,
  operador_cpf VARCHAR(20) NOT NULL,
  operador_nome VARCHAR(150) DEFAULT NULL,
  entrou_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  saiu_em DATETIME DEFAULT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE categorias_lavagem_peca (
  id                                              INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  empresa_cnpj                                    VARCHAR(20) NOT NULL,
  nome                                            VARCHAR(120) NOT NULL,
  descricao                                       TEXT,
  valor_padrao                                    DECIMAL(12,2) DEFAULT 0.00,
  ativo                                           TINYINT(1) NOT NULL DEFAULT 1,
  criado_em                                       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categorias_produto_peca (
  id                                              INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  empresa_cnpj                                    VARCHAR(20) NOT NULL,
  nome                                            VARCHAR(120) NOT NULL,
  criado_em                                       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE empresas_peca (
  id                                              INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  cnpj                                            VARCHAR(20) NOT NULL,
  nome_fantasia                                   VARCHAR(150) NOT NULL,
  razao_social                                    VARCHAR(200) DEFAULT NULL,
  telefone                                        VARCHAR(20) DEFAULT NULL,
  email                                           VARCHAR(150) DEFAULT NULL,
  endereco                                        TEXT,
  cidade                                          VARCHAR(100) DEFAULT NULL,
  estado                                          CHAR(2) DEFAULT NULL,
  cep                                             VARCHAR(10) DEFAULT NULL,
  status                                          ENUM('ativa','inativa','suspensa') NOT NULL DEFAULT 'ativa',
  criado_em                                       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE fornecedores_peca (
  id                                              INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  empresa_cnpj                                    VARCHAR(20) NOT NULL,
  nome                                            VARCHAR(180) NOT NULL,
  cnpj_cpf                                        VARCHAR(20) DEFAULT NULL,
  telefone                                        VARCHAR(20) DEFAULT NULL,
  email                                           VARCHAR(150) DEFAULT NULL,
  endereco                                        TEXT,
  cidade                                          VARCHAR(100) DEFAULT NULL,
  estado                                          CHAR(2) DEFAULT NULL,
  cep                                             VARCHAR(10) DEFAULT NULL,
  obs                                             TEXT,
  ativo                                           TINYINT(1) NOT NULL DEFAULT 1,
  criado_em                                       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE lavadores_peca (
  id                                              INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  empresa_cnpj                                    VARCHAR(20) NOT NULL,
  nome                                            VARCHAR(150) NOT NULL,
  cpf                                             VARCHAR(20) DEFAULT NULL,
  telefone                                        VARCHAR(20) DEFAULT NULL,
  email                                           VARCHAR(150) DEFAULT NULL,
  ativo                                           TINYINT(1) NOT NULL DEFAULT 1,
  criado_em                                       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE lavagens_peca (
  id                                              INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  empresa_cnpj                                    VARCHAR(20) NOT NULL,
  lavador_cpf                                     VARCHAR(20) NOT NULL,
  placa                                           VARCHAR(10) DEFAULT NULL,
  modelo                                          VARCHAR(120) DEFAULT NULL,
  cor                                             VARCHAR(30) DEFAULT NULL,
  categoria_id                                    INT DEFAULT NULL,
  categoria_nome                                  VARCHAR(120) DEFAULT NULL,
  valor                                           DECIMAL(12,2) NOT NULL,
  forma_pagamento                                 VARCHAR(40) DEFAULT 'dinheiro',
  status                                          ENUM('aberta','concluida','cancelada') NOT NULL DEFAULT 'aberta',
  checkin_at                                      DATETIME DEFAULT NULL,
  checkout_at                                     DATETIME DEFAULT NULL,
  observacoes                                     TEXT,
  criado_em                                       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE mov_estoque_peca (
  id                                              INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  empresa_cnpj                                    VARCHAR(20) NOT NULL,
  produto_id                                      INT NOT NULL,
  tipo                                            ENUM('entrada','saida','ajuste') NOT NULL,
  qtd                                             DECIMAL(12,3) NOT NULL,
  origem                                          ENUM('compra','venda','ajuste','os') NOT NULL,
  ref_id                                          INT DEFAULT NULL,
  usuario_cpf                                     VARCHAR(20) DEFAULT NULL,
  criado_em                                       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE orcamentos_peca (
  id                                              INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  empresa_cnpj                                    VARCHAR(20) NOT NULL,
  numero                                          INT NOT NULL,
  cliente_nome                                    VARCHAR(150) DEFAULT NULL,
  cliente_telefone                                VARCHAR(20) DEFAULT NULL,
  cliente_email                                   VARCHAR(150) DEFAULT NULL,
  validade                                        DATE DEFAULT NULL,
  status                                          ENUM('aberto','aprovado','rejeitado','expirado') NOT NULL DEFAULT 'aberto',
  observacoes                                     TEXT,
  total_bruto                                     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  desconto                                        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_liquido                                   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  criado_em                                       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE orcamento_itens_peca (
  id                                              INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  orcamento_id                                    INT NOT NULL,
  item_tipo                                       ENUM('produto','servico') NOT NULL,
  item_id                                         INT DEFAULT NULL,
  descricao                                       VARCHAR(255) NOT NULL,
  qtd                                             DECIMAL(12,3) NOT NULL DEFAULT 1.000,
  valor_unit                                      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  valor_total                                     DECIMAL(12,2) NOT NULL DEFAULT 0.00
);

CREATE TABLE produtos_peca (
  id                                              INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  empresa_cnpj                                    VARCHAR(20) NOT NULL,
  categoria_id                                    INT DEFAULT NULL,
  nome                                            VARCHAR(180) NOT NULL,
  sku                                             VARCHAR(60) DEFAULT NULL,
  ean                                             VARCHAR(20) DEFAULT NULL,
  marca                                           VARCHAR(80) DEFAULT NULL,
  unidade                                         VARCHAR(10) DEFAULT 'UN',
  preco_custo                                     DECIMAL(12,2) DEFAULT 0.00,
  preco_venda                                     DECIMAL(12,2) NOT NULL,
  estoque_atual                                   DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  estoque_minimo                                  DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  ativo                                           TINYINT(1) NOT NULL DEFAULT 1,
  criado_em                                       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE solicitacoes_empresas_peca (
  id                                              INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  nome_fantasia                                   VARCHAR(150) NOT NULL,
  cnpj                                            VARCHAR(20) DEFAULT NULL,
  telefone                                        VARCHAR(20) DEFAULT NULL,
  email                                           VARCHAR(150) DEFAULT NULL,
  proprietario_nome                               VARCHAR(150) NOT NULL,
  proprietario_email                              VARCHAR(150) NOT NULL,
  proprietario_senha_hash                         VARCHAR(255) DEFAULT NULL,
  status                                          ENUM('pendente','aprovada','recusada') NOT NULL DEFAULT 'pendente',
  token_aprovacao                                 VARCHAR(64) DEFAULT NULL,
  criado_em                                       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE usuarios_peca (
  id                                              INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  empresa_cnpj                                    VARCHAR(20) DEFAULT NULL,
  nome                                            VARCHAR(120) NOT NULL,
  email                                           VARCHAR(150) NOT NULL,
  cpf                                             VARCHAR(20) DEFAULT NULL,
  telefone                                        VARCHAR(20) DEFAULT NULL,
  senha                                           VARCHAR(255) NOT NULL,
  perfil                                          ENUM('super_admin','dono','funcionario') NOT NULL DEFAULT 'funcionario',
  tipo_funcionario                                ENUM('lavajato','autopeca','administrativo','caixa','estoque') DEFAULT 'lavajato',
  status                                          TINYINT(1) NOT NULL DEFAULT 1,
  precisa_redefinir_senha                         TINYINT(1) NOT NULL DEFAULT 0,
  senha_atualizada_em                             DATETIME DEFAULT NULL,
  ultimo_login_em                                 DATETIME DEFAULT NULL,
  falhas_login                                    INT NOT NULL DEFAULT 0,
  criado_em                                       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE usuarios_redefinicao_senha_peca (
  id                                              INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  usuario_id                                      INT DEFAULT NULL,
  email                                           VARCHAR(150) NOT NULL,
  token                                           VARCHAR(64) NOT NULL,
  otp                                             VARCHAR(6) DEFAULT NULL,
  expiracao                                       DATETIME NOT NULL,
  usado_em                                        DATETIME DEFAULT NULL,
  ip_solicitante                                  VARCHAR(45) DEFAULT NULL,
  user_agent                                      VARCHAR(255) DEFAULT NULL,
  criado_em                                       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE vendas_peca (
  id                                              INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  empresa_cnpj                                    VARCHAR(20) NOT NULL,
  vendedor_cpf                                    VARCHAR(20) NOT NULL,
  origem                                          ENUM('balcao','lavajato','orcamento') NOT NULL DEFAULT 'balcao',
  status                                          ENUM('aberta','fechada','cancelada') NOT NULL DEFAULT 'fechada',
  total_bruto                                     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  desconto                                        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_liquido                                   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  forma_pagamento                                 VARCHAR(40) DEFAULT 'dinheiro',
  criado_em                                       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE venda_itens_peca (
  id                                              INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
  venda_id                                        INT NOT NULL,
  item_tipo                                       ENUM('produto','servico') NOT NULL,
  item_id                                         INT NOT NULL,
  descricao                                       VARCHAR(255) NOT NULL,
  qtd                                             DECIMAL(12,3) NOT NULL DEFAULT 1.000,
  valor_unit                                      DECIMAL(12,2) NOT NULL,
  valor_total                                     DECIMAL(12,2) NOT NULL
);
