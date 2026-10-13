const nomeUsuario = document.getElementById("nomeUsuario");

document.addEventListener("DOMContentLoaded", async () => {

    // faz o fetch para a api
    const resposta = await fetch("/monstroguelselnius/api/usuario/eu", {
        method: "GET"
    });

    // recebe o resultado
    const resultado = await resposta.json();
    
    nomeUsuario.innerText = resultado.nome;
})

const botaoSair = document.getElementById("sair");
botaoSair.addEventListener("click", async () => {
    // faz o fetch para a api
    const resposta = await fetch("/monstroguelselnius/api/usuario/sair", {
        method: "POST"
    });

    // recebe o resultado
    const resultado = await resposta.json();

    if (resultado.refresh) window.location.reload();
})

const botaoExcluir = document.getElementById("excluir");
botaoExcluir.addEventListener("click", async () => {
    // faz o fetch para a api
    const resposta = await fetch(`/monstroguelselnius/api/usuario//excluir`, {
        method: "DELETE"
    });

    // recebe o resultado
    const resultado = await resposta.json();
    if (resultado.refresh) window.location.reload();
})

const formAtualizar = document.getElementById("formAtualizar");
formAtualizar.addEventListener("submit", async (e) => {

    e.preventDefault();

    // resgata os dados do formulario
    const dados = new FormData(formAtualizar);

    // faz o fetch para a api
    const resposta = await fetch("/monstroguelselnius/api/usuario/atualizar", {
        method: "PATCH",
        body: dados
    });

    // recebe o resultado
    const resultado = await resposta.json();
    if (resultado.refresh) window.location.reload();
})