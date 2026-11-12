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

    echo "<h1>Seed de Efeitos</h1>";
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

  public function run(): void {
    // remove os arquivos para não ficar dando append nos que já existem
    $this->removerSeeds();

    foreach ($this->arquivosSeeds as $seed) {
      $metodo = $seed["metodo"];

      if (method_exists($this, $metodo)) $this->$metodo();
    }

  }
}