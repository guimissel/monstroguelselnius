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

    if(resultado.status == 200)
    {
        window.location = "/monstroguelselnius/login";
    }
})