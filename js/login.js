
//esta culicagada de mierda ni la miren. Si veo que usaron algo de esto para el proyecto LOS MATO
//LOS FULMINO. LOS DESTROSO. LOS MANDO FUSILAR
//que le pasaba

async function _on_click_button_login() {
    const listTypeUser = ["vecino", "camionero", "municipal", "root"];
    let ci = input_tag.value;
    let password = input_password.value;
    let typeUser = select_user.value;

    let datosAEnviar = {
        TYPEUSER: listTypeUser[typeUser],
        CI: ci,
        PASSWORD: password
    };

    
    const respuestaCruda = await fetch("./api/users", {
            method: "POST",
    headers: {
        "Content-Type": "application/json"
    },
    body: JSON.stringify(datosAEnviar)
    });
        
    console.log(await respuestaCruda.json());
    return;

    if (respuestaCruda.ok) {
        let datos = await respuestaCruda.json();

        console.log("Login exitoso. Datos del usuario:", datos);
            
            
    } else {
        let datosError = await respuestaCruda.json();
            
        if (respuestaCruda.status === 401) {
                alert(datosError.error);
        } else if (respuestaCruda.status === 500) {
                alert("Hubo un problema con el servidor. Intenta mas tarde.");
        }
    }

   
}