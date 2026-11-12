<?php

/*
Ordem das insercoes:

efeito
monstro
tipo
eficaz, fraqueza e imune_tipo
tipo_monstro
imagem_monstro
ataque
ataque_possivel_monstro
*/

class MontadorSeed {
  private string $diretorioSeeds;
  private array $arquivosSeeds;
  private string $urlBase;

  public function __construct(string $diretorioSeeds) {
    $this->diretorioSeeds = $diretorioSeeds;
    if (!is_dir($this->diretorioSeeds)) mkdir($this->diretorioSeeds, 0755);

    $this->arquivosSeeds = [
      "efeito" => [
        "arquivo" => "1-efeito",
        "metodo" => "seedEfeito"
      ],
      "monstro" => [
        "arquivo" => "2-monstro",
        "metodo" => "seedMonstro"
      ],
      "evolucao" => [
        "arquivo" => "3-evolucao",
        "metodo" => "seedEvolucao"
      ],
      "tipo" => [
        "arquivo" => "4-tipo",
        "metodo" => "seedTipo"
      ],
      "relacoes_tipos" => [
        "arquivo" => "5-relacoes_tipos",
        "metodo" => "seedRelacoesTipos"
      ],
      "tipo_monstro" => [
        "arquivo" => "6-tipo_monstro",
        "metodo" => "seedTipoMonstro"
      ],
      "imagem_monstro" => [
        "arquivo" => "7-imagem_monstro",
        "metodo" => "seedImagemMonstro"
      ],
      "ataque" => [
        "arquivo" => "8-ataque",
        "metodo" => "seedAtaque"
      ],
      "ataque_possivel_monstro" => [
        "arquivo" => "9-ataque_possivel_monstro",
        "metodo" => "seedAtaquePossivelMonstro"
      ]
    ];

    $this->montaNomeSeed();

    $this->urlBase = "https://pokeapi.co/api/v2/";
  }

  private function salvarTextoNaSeed(string $nomeSeed, string $texto): void {
    $arquivo = $this->diretorioSeeds . $nomeSeed;
    file_put_contents($arquivo, $texto . ";\n", FILE_APPEND);
  }

  private function fazerRequisicao(string $url): array {
    $resposta = file_get_contents($url);

    usleep(500000);

    return json_decode($resposta, true);
  }

  private function extrairIdUrl(string $url): int {
    $url = rtrim($url, "/");
    $partesUrl = explode("/", $url);

    return (int) end($partesUrl);
  }

  private function montaNomeSeed(): void {
    foreach ($this->arquivosSeeds as $nome => $arquivo) {
      $this->arquivosSeeds[$nome]["arquivo"] .= ".sql";
    }
  }

  private function removerSeeds(): void {
    foreach ($this->arquivosSeeds as $seed) {
      if (file_exists($this->diretorioSeeds . $seed["arquivo"])) unlink($this->diretorioSeeds . $seed["arquivo"]);
    }
  }

  private function seedEfeito(): void {
    $dados = $this->fazerRequisicao($this->urlBase . "move-ailment");

    echo "<h1>Efeito</h1>";
    foreach ($dados["results"] as $efeito) {
      $sql = "INSERT INTO efeito(nome) VALUES ('{$efeito['name']}')";

      echo $sql . "<br/>";

      $this->salvarTextoNaSeed($this->arquivosSeeds["efeito"]["arquivo"], $sql);
    }
  }

  private function seedMonstro(): void {
    // primeiro salva os tipos de imagem
    $tiposImagem = "INSERT INTO tipo_imagem(id_tipo_imagem, nome) VALUES
(1, 'front_default'),
(2, 'back_default'),
(3, 'front_batalha'),
(4, 'back_batalha')";

      $this->salvarTextoNaSeed($this->arquivosSeeds["imagem_monstro"]["arquivo"], $tiposImagem);
    echo "<h1>Monstro</h1>";
    for ($id = 1; $id <= 151; $id++) {
      $url = "{$this->urlBase}pokemon/{$id}/";
      $dados = $this->fazerRequisicao($url);

      if (empty($dados)) continue;

      // 1. Extracao dos dados do pokemon
      $nome = $dados["name"];
      $vidaBase = $dados["stats"][0]["base_stat"];
      $ataqueBase = $dados["stats"][1]["base_stat"];
      $defesaBase = $dados["stats"][2]["base_stat"];
      $ataqueEspBase = $dados["stats"][3]["base_stat"];
      $defesaEspBase = $dados["stats"][4]["base_stat"];
      $velocidadeBase = $dados["stats"][5]["base_stat"];

      $sql = "INSERT INTO monstro(nome, vida_base, ataque_base, defesa_base, ataque_esp_base, defesa_esp_base, velocidade_base) VALUES ('{$nome}', {$vidaBase}, {$ataqueBase}, {$defesaBase}, {$ataqueEspBase}, {$defesaEspBase}, {$velocidadeBase})";

      echo $sql . "<br/>";
      
      $this->salvarTextoNaSeed($this->arquivosSeeds["monstro"]["arquivo"], $sql);

      // 2. Imagens do pokemon
      $imagens = [
        1 => $dados["sprites"]["front_default"],
        2 => $dados["sprites"]["back_default"],
        3 => $dados["sprites"]["other"]["showdown"]["front_default"],
        4 => $dados["sprites"]["other"]["showdown"]["back_default"]
      ];

      foreach ($imagens as $tipoImagem => $imagem) {
        if (empty($imagem)) continue;

        $sqlImagem = "INSERT INTO imagem_monstro(id_monstro, caminho, id_tipo_imagem) VALUES ({$id}, '{$imagem}', {$tipoImagem})";

        $this->salvarTextoNaSeed($this->arquivosSeeds["imagem_monstro"]["arquivo"], $sqlImagem);
      }
    }
  }

  private function seedEvolucao(): void {
    echo "<h1>Evolução</h1>";

    for ($i = 1; $i <= 78; $i++) {
      $url = "{$this->urlBase}evolution-chain/{$i}";
      $dados = $this->fazerRequisicao($url);

      $nivel = 1;

      if (empty($dados)) continue;
      
      $cadeia = $dados["chain"];

      // processar cadeia evolutiva
      $this->processarCadeiaEvolutiva($cadeia, $nivel);
    }
  }

  private function processarCadeiaEvolutiva(array $cadeia, int $nivel): void {
    $idBase = $this->extrairIdUrl($cadeia["species"]["url"]);

    // se nao for da primeira geracao, pula
    if ($idBase > 151) return;

    // percorrer evolucoes diretas
    foreach ($cadeia["evolves_to"] as $evolucaoDireta) {
      $idEvolucao = $this->extrairIdUrl($evolucaoDireta["species"]["url"]);

      if ($idEvolucao > 151) continue;

      $pokedolaresNecessarios = $nivel * 2500;

      $sqlEvolucaoDireta = "INSERT INTO evolucao(id_monstro_base, id_monstro_evoluido, pokedolares_necessarios) VALUES ({$idBase}, {$idEvolucao}, {$pokedolaresNecessarios})";

      $this->salvarTextoNaSeed($this->arquivosSeeds["evolucao"]["arquivo"], $sqlEvolucaoDireta);

      echo $sqlEvolucaoDireta . "<br/>";

      $this->processarCadeiaEvolutiva($evolucaoDireta, $nivel + 1);
    }
  }

  private function seedTipo(): void {
    echo "<h1>Tipo</h1>";

    $url = "{$this->urlBase}type/";

    $dados = $this->fazerRequisicao($url);
    $tipos = $dados["results"];

    foreach ($tipos as $tipo) {
      $id = $this->extrairIdUrl($tipo["url"]);
      $nome = $tipo["name"];

      $sql = "INSERT INTO tipo(id_tipo, nome) VALUES ({$id}, '{$nome}')";

      echo $sql . "<br/>";

      $this->salvarTextoNaSeed($this->diretorioSeeds . $this->arquivosSeeds["tipo"]["arquivo"], $sql);
    }
  }

  private function seedRelacoesTipos(): void {
    echo "<h1>Relações Tipos</h1>";

    for ($idTipo = 1; $idTipo <= 19; $idTipo++) {
      $url = "{$this->urlBase}type/{$idTipo}/";

      $dados = $this->fazerRequisicao($url);
      $relacoes = $dados["damage_relations"];

      // Imunidades
      echo "<h2>Imunidades</h2>";
      $this->salvarTextoNaSeed($this->diretorioSeeds . $this->arquivosSeeds["relacoes_tipos"]["arquivo"], "\n-- Imunidades");
      foreach ($relacoes["no_damage_to"] as $tipoAlvo) {
        $idAlvo = $this->extrairIdUrl($tipoAlvo["url"]);
        $sql = "INSERT INTO imunidades_tipo(id_tipo, id_tipo_imunidade) VALUES ({$idTipo}, {$idAlvo})";

        echo $sql . "<br/>";

        $this->salvarTextoNaSeed($this->diretorioSeeds . $this->arquivosSeeds["relacoes_tipos"]["arquivo"], $sql);
      }

      // Eficacias
      echo "<h2>Eficacias</h2>";
      $this->salvarTextoNaSeed($this->diretorioSeeds . $this->arquivosSeeds["relacoes_tipos"]["arquivo"], "\n-- Eficacias");
      foreach ($relacoes["double_damage_to"] as $tipoAlvo) {
        $idAlvo = $this->extrairIdUrl($tipoAlvo["url"]);
        $sql = "INSERT INTO eficacias_tipo(id_tipo, id_tipo_eficacia) VALUES ({$idTipo}, {$idAlvo})";

        echo $sql . "<br/>";

        $this->salvarTextoNaSeed($this->diretorioSeeds . $this->arquivosSeeds["relacoes_tipos"]["arquivo"], $sql);
      }

      // Fraquezas
      echo "<h2>Tipos que o tipo e eficaz</h2>";
      $this->salvarTextoNaSeed($this->diretorioSeeds . $this->arquivosSeeds["relacoes_tipos"]["arquivo"], "\n-- Fraquezas");
      foreach ($relacoes["half_damage_to"] as $tipoAlvo) {
        $idAlvo = $this->extrairIdUrl($tipoAlvo["url"]);
        $sql = "INSERT INTO fraquezas_tipo(id_tipo, id_tipo_eficacia) VALUES ({$idTipo}, {$idAlvo})";

        echo $sql . "<br/>";

        $this->salvarTextoNaSeed($this->diretorioSeeds . $this->arquivosSeeds["relacoes_tipos"]["arquivo"], $sql);
      }
    }
  }

  private function seedTipoMonstro(): void {
    
  }

  public function run(): void {
    // remove os arquivos para não ficar dando append nos que já existem
    $this->removerSeeds();

    foreach ($this->arquivosSeeds as $seed) {
      $metodo = $seed["metodo"];

      if (method_exists($this, $metodo)) $this->$metodo();
    }

  }
}