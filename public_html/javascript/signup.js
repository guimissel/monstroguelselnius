const formulario = document.getElementById("formularioSignup");

// adicionar evento de envio
formulario.addEventListener("submit", async (e) => {

    // evita de ser redirecionado
    e.preventDefault();

    // resgata os dados do formulario
    const dados = new FormData(formulario);

    // envia a requisição fetch
    const resposta = await fetch("/monstroguelselnius/api/usuario/criar", {
        method: "POST",
        body: dados
    });

    // recebe o resultado
    const resultado = await resposta.json();

    if (resultado.status == 400)
    {
        alert(resultado.mensagem);
        return;
    }
    console.log(resultado);
})