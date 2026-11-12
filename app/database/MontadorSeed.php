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
      "1-efeitos",
      "2-monstro",
      "3-tipo",
      "4-relacoes_tipos",
      "5-tipo_monstro",
      "6-imagem_monstro",
      "7-ataque",
      "8-ataque_possivel_monstro"  
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

    return end($partesUrl);
  }

  private function montaNomeSeed(): void {
    for ($i = 0; $i < count($this->arquivosSeeds); $i++) {
      $this->arquivosSeeds[$i] .= ".sql";;
    }
  }

  private function removerSeeds(): void {
    foreach ($this->arquivosSeeds as $arquivo) {
      if (file_exists($this->diretorioSeeds . $arquivo)) unlink($arquivo);
    }
  }

  private function seedEfeito(): void {
    $resposta = $this->fazerRequisicao($this->urlBase . "move-ailment");

    foreach ($resposta["results"] as $efeito) {
      $sql = "INSERT INTO efeito(nome) VALUES ('{$efeito['name']}')";
      echo $sql . "\n";
      $this->salvarTextoNaSeed($this->arquivosSeeds[0], $sql);
    }
  }

  public function run(): void {
    $this->seedEfeito();
  }
}