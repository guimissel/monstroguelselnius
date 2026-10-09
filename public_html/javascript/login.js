const formulario = document.getElementById("formularioLogin");
const mensagem = document.getElementById("mensagem");
const pMensagem = document.getElementById("textoMensagem");

// adicionar evento de envio
formulario.addEventListener("submit", async (e) => {

    // evita de ser redirecionado
    e.preventDefault();

    // resgata os dados do formulario
    const dados = new FormData(formulario);

    // envia a requisição fetch
    const resposta = await fetch("/monstroguelselnius/api/usuario/entrar", {
        method: "POST",
        body: dados
    });

    // recebe o resultado
    const resultado = await resposta.json();

    // remove caso tenha ocorrido um erro antes
    if (mensagem.classList.contains("erro")) mensagem.classList.remove("erro");

    // se resultado for êxito
    if(resultado.status == 200) 
    {
        mensagem.classList.add("sucesso");
    }

    // caso dde erro
    else 
    {
        mensagem.classList.add("erro");
    }

    pMensagem.innerText = resultado.mensagem;
    console.log(resultado)
})