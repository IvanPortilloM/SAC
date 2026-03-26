// js/modules/api.js

export async function fetchUserData(forceRefresh = false) {
    const url = `api/get_user_data.php${forceRefresh ? '?force_refresh=true' : ''}`;
    try {
        const response = await fetch(url);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return await response.json();
    } catch (error) {
        console.error("Error fetching user data:", error);
        return { success: false, error: error.message };
    }
}

export async function fetchBeneficios(tipo, anio) {
    // CORRECCIÓN: Apuntar a la carpeta 'api/'
    const url = tipo === 'excedentes' ? 'api/get_excedentes.php' : 'api/get_rifa.php'; 

    const formData = new FormData();
    formData.append('anio', anio);

    const response = await fetch(url, {
        method: 'POST',
        body: formData
    });

    if (!response.ok) {
        // Esto nos ayudará a ver en la consola si es un 404 (No encontrado) o 500 (Error servidor)
        throw new Error(`Error del servidor: ${response.status} ${response.statusText}`);
    }
    
    return await response.text();
}

export async function postUpdateProfile(data) {
    const response = await fetch('api/update_profile.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    return await response.json();
}

export async function postLoanRequest(data) {
    const response = await fetch('api/request_loan.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    return await response.json();
}