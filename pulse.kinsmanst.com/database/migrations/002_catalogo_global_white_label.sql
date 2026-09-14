SET NAMES utf8mb4;

ALTER TABLE professionals
  ADD COLUMN whatsapp VARCHAR(30) NULL AFTER primary_color,
  ADD COLUMN instagram VARCHAR(120) NULL AFTER whatsapp,
  ADD COLUMN welcome_message VARCHAR(255) NULL AFTER instagram,
  ADD COLUMN footer_text VARCHAR(180) NULL AFTER welcome_message;

ALTER TABLE professionals
  ADD COLUMN subscription_plan ENUM('basic','plus','premium') NOT NULL DEFAULT 'basic' AFTER footer_text,
  ADD COLUMN subscription_status ENUM('trial','active','past_due','cancelled') NOT NULL DEFAULT 'trial' AFTER subscription_plan;

CREATE TABLE IF NOT EXISTS professional_subscriptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  professional_id BIGINT UNSIGNED NOT NULL,
  plan_code ENUM('basic','plus','premium') NOT NULL,
  price_cents INT UNSIGNED NOT NULL,
  status ENUM('trial','active','past_due','cancelled') NOT NULL DEFAULT 'trial',
  starts_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ends_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_subscription_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_subscription_professional FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE,
  INDEX idx_subscription_prof_status (professional_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS global_exercises (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL UNIQUE,
  muscle_group VARCHAR(120) NOT NULL,
  equipment VARCHAR(120) NULL,
  difficulty ENUM('iniciante','intermediario','avancado') NOT NULL DEFAULT 'iniciante',
  instructions TEXT NOT NULL,
  image_path VARCHAR(255) NULL,
  video_url VARCHAR(500) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_global_exercise_search (muscle_group,name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO global_exercises (name,muscle_group,equipment,difficulty,instructions) VALUES
('Agachamento livre','Pernas e glúteos','Peso livre','iniciante','Mantenha os pés na largura dos ombros, abdômen firme e coluna neutra. Desça controlando os joelhos e retorne empurrando o chão.'),
('Agachamento goblet','Pernas e glúteos','Halter','iniciante','Segure o halter junto ao peito, mantenha o tronco firme, desça com controle e suba estendendo quadris e joelhos.'),
('Agachamento sumô','Adutores e glúteos','Halter','iniciante','Afaste os pés além dos ombros e aponte as pontas para fora. Desça mantendo os joelhos alinhados aos pés.'),
('Leg press 45°','Quadríceps e glúteos','Leg press','iniciante','Apoie toda a coluna, posicione os pés na plataforma e desça sem retirar o quadril do banco. Não trave os joelhos.'),
('Cadeira extensora','Quadríceps','Máquina extensora','iniciante','Alinhe os joelhos ao eixo da máquina. Estenda as pernas com controle e retorne sem deixar o peso bater.'),
('Mesa flexora','Posterior de coxa','Máquina flexora','iniciante','Mantenha o quadril apoiado, flexione os joelhos aproximando os calcanhares e retorne lentamente.'),
('Cadeira flexora','Posterior de coxa','Máquina flexora','iniciante','Ajuste o encosto e o rolo, flexione os joelhos sem tirar o quadril do assento e controle a volta.'),
('Stiff com halteres','Posterior e glúteos','Halteres','intermediario','Leve o quadril para trás com joelhos levemente flexionados, coluna neutra e halteres próximos às pernas.'),
('Levantamento terra romeno','Posterior e glúteos','Barra','intermediario','Desça a barra rente às pernas levando o quadril para trás. Suba contraindo glúteos sem hiperestender a lombar.'),
('Afundo alternado','Pernas e glúteos','Peso corporal','iniciante','Dê um passo à frente, desça os dois joelhos com controle e empurre o chão para retornar.'),
('Passada caminhando','Pernas e glúteos','Halteres','intermediario','Avance alternando as pernas, mantenha o tronco ereto e o joelho da frente alinhado ao pé.'),
('Elevação pélvica','Glúteos','Banco e barra','iniciante','Apoie as escápulas, mantenha o queixo recolhido e eleve o quadril contraindo os glúteos.'),
('Abdução de quadril','Glúteo médio','Máquina abdutora','iniciante','Mantenha o tronco estável, abra as pernas sem impulso e retorne controlando.'),
('Panturrilha em pé','Panturrilhas','Máquina ou peso corporal','iniciante','Eleve os calcanhares ao máximo, pause no alto e desça lentamente até alongar a panturrilha.'),
('Supino reto com barra','Peitoral','Barra e banco','intermediario','Mantenha pés firmes, escápulas apoiadas e desça a barra até próximo ao peito. Empurre sem tirar os ombros do banco.'),
('Supino reto com halteres','Peitoral','Halteres e banco','iniciante','Desça os halteres com cotovelos controlados e empurre mantendo punhos alinhados.'),
('Supino inclinado com halteres','Peitoral superior','Halteres e banco inclinado','intermediario','Apoie as costas, desça os halteres lateralmente ao peito e empurre sem elevar os ombros.'),
('Crucifixo com halteres','Peitoral','Halteres e banco','iniciante','Com cotovelos levemente flexionados, abra os braços até alongar o peito e feche sem bater os halteres.'),
('Crossover','Peitoral','Polia','intermediario','Incline levemente o tronco e aproxime as mãos à frente mantendo os cotovelos semiflexionados.'),
('Flexão de braços','Peitoral e tríceps','Peso corporal','iniciante','Mantenha corpo alinhado, abdômen firme e desça o peito entre as mãos. Empurre o chão para retornar.'),
('Puxada frontal','Costas','Polia alta','iniciante','Puxe a barra em direção à parte alta do peito, aproximando as escápulas e evitando balançar o tronco.'),
('Remada baixa','Costas','Polia baixa','iniciante','Mantenha a coluna neutra, puxe o pegador em direção ao abdômen e retorne estendendo os braços com controle.'),
('Remada curvada com barra','Costas','Barra','intermediario','Incline o tronco com coluna neutra e puxe a barra em direção ao abdômen sem usar impulso.'),
('Remada unilateral','Costas','Halter e banco','iniciante','Apoie uma mão e um joelho, puxe o halter em direção ao quadril e controle a descida.'),
('Pulldown com braços estendidos','Dorsais','Polia alta','intermediario','Mantenha os braços quase estendidos e leve a barra até as coxas usando os dorsais.'),
('Desenvolvimento com halteres','Ombros','Halteres','iniciante','Com abdômen firme, empurre os halteres acima da cabeça e desça até a linha dos ombros.'),
('Elevação lateral','Ombros','Halteres','iniciante','Eleve os braços lateralmente até a linha dos ombros sem encolher o trapézio e desça lentamente.'),
('Elevação frontal','Ombros','Halteres','iniciante','Eleve os halteres à frente até a altura dos ombros mantendo o tronco estável.'),
('Face pull','Ombros posteriores','Corda na polia','intermediario','Puxe a corda em direção ao rosto, abrindo as mãos e aproximando as escápulas.'),
('Rosca direta','Bíceps','Barra','iniciante','Mantenha os cotovelos junto ao corpo, flexione os braços sem balançar e desça controlando.'),
('Rosca alternada','Bíceps','Halteres','iniciante','Flexione um braço por vez, mantenha o cotovelo estável e evite inclinar o tronco.'),
('Rosca martelo','Bíceps e antebraço','Halteres','iniciante','Segure os halteres com palmas voltadas para dentro e flexione os cotovelos sem movimentar os ombros.'),
('Tríceps na polia','Tríceps','Polia e barra','iniciante','Mantenha os cotovelos fixos ao lado do corpo, estenda os braços e retorne devagar.'),
('Tríceps francês','Tríceps','Halter','intermediario','Segure o halter acima da cabeça, flexione os cotovelos levando-o para trás e estenda os braços.'),
('Tríceps testa','Tríceps','Barra e banco','intermediario','Mantenha os braços apontados para cima, flexione somente os cotovelos e estenda com controle.'),
('Prancha frontal','Core','Peso corporal','iniciante','Apoie antebraços e pontas dos pés, mantenha corpo alinhado e abdômen contraído sem prender a respiração.'),
('Prancha lateral','Core','Peso corporal','iniciante','Apoie o antebraço, alinhe ombro, quadril e pés e mantenha o quadril elevado.'),
('Abdominal crunch','Abdômen','Colchonete','iniciante','Contraia o abdômen elevando as escápulas, mantenha a lombar apoiada e retorne lentamente.'),
('Abdominal bicicleta','Abdômen','Colchonete','intermediario','Alterne cotovelo e joelho opostos, mantendo a lombar apoiada e o movimento controlado.'),
('Burpee adaptado','Corpo inteiro','Peso corporal','intermediario','Agache, apoie as mãos, leve os pés para trás, retorne e fique em pé. Faça sem perder o alinhamento da coluna.'),
('Polichinelo','Cardio','Peso corporal','iniciante','Salte abrindo pernas e elevando os braços. Amorteça a aterrissagem e mantenha ritmo confortável.'),
('Corrida estacionária','Cardio','Peso corporal','iniciante','Corra no lugar com postura ereta, aterrissagem leve e braços acompanhando o movimento.'),
('Mountain climber','Core e cardio','Peso corporal','intermediario','Em posição de prancha, alterne os joelhos em direção ao peito mantendo quadril estável.'),
('Mobilidade de quadril 90/90','Mobilidade','Colchonete','iniciante','Sente-se com os joelhos flexionados e alterne ambos os lados com controle, sem forçar a amplitude.'),
('Mobilidade torácica em quatro apoios','Mobilidade','Colchonete','iniciante','Em quatro apoios, leve uma mão à nuca e gire o cotovelo para cima acompanhando com o olhar.');
