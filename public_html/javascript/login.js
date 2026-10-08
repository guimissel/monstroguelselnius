const formulario = document.getElementById("formularioLogin");

// adicionar evento de envio
formulario.addEventListener("submit", async (e) => {

    // evita de ser redirecionado
    e.preventDefault();

    console.log("login");
})