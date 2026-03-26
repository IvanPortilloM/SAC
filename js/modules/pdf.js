// js/modules/pdf.js
import { formatNumber } from './utils.js';

export function generateStatementPDF(user, financialData) {
    if (!window.jspdf) {
        alert("Error: Librería PDF no cargada. Recarga la página.");
        return;
    }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    // ==================== 1. LOGO Y ENCABEZADO ====================
    
    // Intentamos obtener el logo desde el HTML (así no dependemos de rutas externas)
    // Buscamos la imagen por su alt o clase conocida
    const logoImg = document.querySelector('img[alt="Logo"]');
    
    if (logoImg) {
        try {
            // Dibujamos el logo: (imagen, formato, x, y, ancho, alto)
            doc.addImage(logoImg, 'PNG', 14, 10, 25, 18); 
        } catch (e) {
            console.warn("No se pudo cargar el logo en el PDF", e);
        }
    }

    // Texto del Encabezado (Alineado a la derecha del logo o centrado)
    doc.setFont("helvetica", "bold");
    doc.setFontSize(18);
    doc.setTextColor(0, 51, 102); // Azul oscuro corporativo
    doc.text("ASOCIACIÓN DE DESARROLLO INTEGRAL", 45, 18);
    doc.setFontSize(14);
    doc.text("GRUPO GRANJAS MARINAS (A.D.I - GGM)", 45, 25);
    
    // Línea divisoria
    doc.setDrawColor(0, 51, 102);
    doc.setLineWidth(0.5);
    doc.line(14, 38, 196, 38);

    // Subtítulo
    doc.setFont("helvetica", "normal");
    doc.setFontSize(10);
    doc.setTextColor(0);
    const fechaImpresion = new Date().toLocaleDateString('es-HN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute:'2-digit' });
    doc.text(`Estado de Cuenta Consolidado al: ${financialData.fecha_procesado || fechaImpresion}`, 196, 45, { align: 'right' });

    // ==================== 2. FICHA DEL ASOCIADO ====================
    const startY = 50;
    
    // Caja de fondo para datos del usuario
    doc.setFillColor(245, 247, 250); // Gris azulado muy claro
    doc.setDrawColor(200, 200, 200);
    doc.roundedRect(14, startY, 182, 35, 3, 3, 'FD');

    doc.setFontSize(11);
    doc.setFont("helvetica", "bold");
    doc.setTextColor(0, 51, 102);
    doc.text(`${user.identity_number || ''} - ${user.nombres} ${user.apellidos}`, 20, startY + 10);
    
    doc.setFont("helvetica", "normal");
    doc.setFontSize(9);
    doc.setTextColor(50);
    
    // Columna 1
    doc.text(`Dirección:`, 20, startY + 20);
    doc.setFont("helvetica", "bold");
    doc.text(`${user.direccion || 'No registrada'}`, 40, startY + 20);
    
    // Columna 2
    doc.setFont("helvetica", "normal");
    doc.text(`Teléfono:`, 120, startY + 20);
    doc.setFont("helvetica", "bold");
    doc.text(`${user.telefono || '--'}`, 140, startY + 20);

    // Columna 1 - Fila 2
    doc.setFont("helvetica", "normal");
    doc.text(`Correo:`, 20, startY + 28);
    doc.setFont("helvetica", "bold");
    doc.text(`${user.email || '--'}`, 40, startY + 28);

    let currentY = startY + 45;

    // ==================== 3. TABLAS DE DETALLE ====================
    if (financialData.detalle_grupos) {
        financialData.detalle_grupos.forEach(grupo => {
            const nombreGrupo = grupo.nombre.toUpperCase().trim();
            
            // Título de la sección
            doc.setFontSize(12);
            doc.setFont("helvetica", "bold");
            doc.setTextColor(0, 51, 102);
            doc.text(nombreGrupo, 14, currentY);
            currentY += 2;

            // Columnas dinámicas
            let columns = [];
            let body = [];
            
            if (nombreGrupo.includes("CREDITOS")) {
                columns = [
                    { header: 'No.', dataKey: 'op' },
                    { header: 'Descripción', dataKey: 'desc' },
                    { header: 'Monto Original', dataKey: 'principal', halign: 'right' },
                    { header: 'Saldo Actual', dataKey: 'saldo', halign: 'right' },
                    { header: 'Cuota', dataKey: 'cuota', halign: 'right' },
                    { header: 'Pagos', dataKey: 'pagos', halign: 'center' }
                ];
                
                grupo.registros.forEach(reg => {
                    body.push({
                        op: reg.NumOperacion || reg.Operacion,
                        desc: reg.Descripci,
                        principal: formatNumber(reg.Principal),
                        saldo: formatNumber(reg.saldo),
                        cuota: formatNumber(reg.Cuota),
                        pagos: reg.Pagos || '0'
                    });
                });
            } else {
                columns = [
                    { header: 'Ref', dataKey: 'op' },
                    { header: 'Descripción', dataKey: 'desc' },
                    { header: 'Ahorro Acumulado', dataKey: 'principal', halign: 'right' },
                    { header: 'Deducción', dataKey: 'cuota', halign: 'right' }
                ];

                grupo.registros.forEach(reg => {
                    body.push({
                        op: reg.NumOperacion || reg.Operacion,
                        desc: reg.Descripci,
                        principal: formatNumber(reg.Principal),
                        cuota: formatNumber(reg.Cuota)
                    });
                });
            }

            // Generar Tabla con estilos mejorados
            doc.autoTable({
                startY: currentY,
                head: [columns.map(c => c.header)],
                body: body.map(row => columns.map(c => row[c.dataKey])),
                theme: 'striped',
                styles: { fontSize: 8, cellPadding: 3, valign: 'middle' },
                headStyles: { 
                    fillColor: [23, 37, 84], // Azul muy oscuro (Tailwind blue-950)
                    textColor: 255, 
                    fontStyle: 'bold',
                    halign: 'center'
                }, 
                columnStyles: {
                    2: { halign: 'right', fontStyle: 'bold' }, // Montos
                    3: { halign: 'right' },
                    4: { halign: 'right' }
                },
                margin: { left: 14, right: 14 }
            });

            // Totales por grupo
            currentY = doc.lastAutoTable.finalY + 2;
            doc.setFillColor(240, 240, 240);
            doc.rect(14, currentY, 182, 8, 'F'); // Barra gris para totales

            doc.setFontSize(9);
            doc.setFont("helvetica", "bold");
            doc.setTextColor(0);
            
            if (nombreGrupo.includes("CREDITOS")) {
                doc.text(`TOTAL SALDO: L. ${formatNumber(grupo.subSaldo)}`, 190, currentY + 5.5, { align: 'right' });
            } else {
                doc.text(`TOTAL ACUMULADO: L. ${formatNumber(grupo.subPrincipal)}`, 190, currentY + 5.5, { align: 'right' });
            }
            
            currentY += 15; // Espacio
        });
    }

    // ==================== 4. PIE DE PÁGINA ====================
    const pageCount = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.setFontSize(8);
        doc.setFont("helvetica", "italic");
        doc.setTextColor(150);
        doc.text(`Página ${i} de ${pageCount}`, 196, 285, { align: 'right' });
        doc.text("Este documento es un reporte generado electrónicamente.", 14, 285);
    }

    doc.save(`Estado_Cuenta_${user.identity_number || 'ADI'}.pdf`);
}