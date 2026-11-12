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
      "tipo" => [
        "arquivo" => "3-tipo",
        "metodo" => "seedTipo"
      ],
      "relacoes_tipos" => [
        "arquivo" => "4-relacoes_tipos",
        "metodo" => "seedRelacoesTipos"
      ],
      "tipo_monstro" => [
        "arquivo" => "5-tipo_monstro",
        "metodo" => "seedTipoMonstro"
      ],
      "imagem_monstro" => [
        "arquivo" => "6-imagem_monstro",
        "metodo" => "seedImagemMonstro"
      ],
      "ataque" => [
        "arquivo" => "7-ataque",
        "metodo" => "seedAtaque"
      ],
      "ataque_possivel_monstro" => [
        "arquivo" => "8-ataque_possivel_monstro",
        "metodo" => "seedAtaquePossivelMonstro"
      ]
    ];

    $this->montaNomeSeed();

    $this->urlBase = "https://pokeapi.co/api/v2/";
  }

  private function salvarTextoNaSeed(string $nomeSeed, string $texto): void {
    $arquivo = $this->diretorioSeeds . $nomeSeed;
    file_put_contents($arquivo, $texto . "\n", FILE_APPEND);
  }

  private function fazerRequisicao(string $url): array {
    $resposta = file_get_contents($url);

    usleep(200000);

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
    $resposta = $this->fazerRequisicao($this->urlBase . "move-ailment");

    echo "<h1>Seed de Efeitos</h1>";
    foreach ($resposta["results"] as $efeito) {
      $sql = "INSERT INTO efeito(nome) VALUES ('{$efeito['name']}')";
      echo $sql . "<br/>";
      $this->salvarTextoNaSeed($this->arquivosSeeds["efeito"]["arquivo"], $sql);
    }
  }

  private function seedMonstro(): void {
    // $resposta = $this->fazerRequisicao($this->)
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