const formulario = document.getElementById("form-registro");
const cedula = document.getElementById("cedula");
const correo = document.getElementById("correo");
const contrasena = document.getElementById("contrasena");
const confirmar = document.getElementById("confirmar_contrasena");
const tieneCuenta = document.getElementById("tiene_cuenta");

// Mostrar los mensajes antes de enviar el formulario.
formulario.noValidate = true;

function validarRegistro() {
    cedula.setCustomValidity(
        cedula.value.trim() ? "" : "Ingrese su cédula."
    );

    correo.setCustomValidity(
        !correo.value.trim() || correo.validity.typeMismatch
            ? "Ingrese un correo electrónico válido."
            : ""
    );

    contrasena.setCustomValidity("");
    confirmar.setCustomValidity("");

    if (!contrasena.value) {
        contrasena.setCustomValidity("Ingrese su contraseña.");
    } else if (!tieneCuenta.checked && Array.from(contrasena.value).length < 8) {
        contrasena.setCustomValidity(
            "La contraseña nueva debe tener al menos 8 caracteres."
        );
    } else if (!tieneCuenta.checked && new TextEncoder().encode(contrasena.value).length > 72) {
        contrasena.setCustomValidity(
            "La contraseña nueva es demasiado larga. Pruebe con una más corta."
        );
    }

    if (!confirmar.value) {
        confirmar.setCustomValidity(
            "Repita la contraseña para confirmarla."
        );
    } else if (contrasena.value !== confirmar.value) {
        confirmar.setCustomValidity("Las contraseñas no coinciden.");
    }
}

formulario.addEventListener("input", validarRegistro);

formulario.addEventListener("submit", function (evento) {
    validarRegistro();

    if (!formulario.reportValidity()) {
        evento.preventDefault();
    }
});