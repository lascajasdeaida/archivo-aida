import subprocess, re, csv, json
def q(sql):
    out=subprocess.run(['mysql','--default-character-set=utf8mb4','-N','-B','aida','-e',sql],capture_output=True,text=True).stdout
    return [l.split('\t') for l in out.splitlines() if l]
filas=[]
def add(cod,col,actual,sug,motivo,accion='Corregir'):
    m=re.match(r'C(\d+)R(\d+)',cod or '')
    caja=int(m.group(1)) if m else int(cod)
    filas.append(dict(caja=caja,registro=cod if m else f'Caja {caja} (todas las filas)',columna=col,actual=actual,sugerido=sug,motivo=motivo,accion=accion))
# 1. Fechas al revés
rev={'C32R4':'04/04/2005','C32R2':'14/12/2004','C29R7':'17/03/2005','C3R1':'17/12/2008','C32R10':'18/04/2005','C32R3':'18/04/2005','C32R6':'19/03/2005','C32R11':'19/04/2005','C32R12':'19/04/2005','C29R1':'21/02/2005','C29R2':'25/02/2005','C32R7':'29/04/2005','C32R5':'30/03/2005','C32R17':'31/12/2004'}
for c,f in q("select codigo, fecha_texto from registro where codigo in (%s)"%','.join("'%s'"%k for k in rev)):
    add(c,'Fecha como dice el documento',f,rev[c],'Se tipeó como aa/mm/dd y Excel la leyó como dd/mm/aa. Escribirla como texto (empezando con un apóstrofo: \'30/03/2005) para que Excel no la convierta.')
revisar=['C10R1','C16R3','C17R23','C17R36','C20R2','C20R7','C20R8','C20R9','C27R4','C27R5','C28R1','C35R16','C35R17','C3R7','C3R8','C41R16','C41R17','C41R4','C42R14','C43R27','C46R18','C49R5','C52R2','C60R19','C64R1']
for c,a,f in q("select codigo, anio_texto, fecha_texto from registro where codigo in (%s)"%','.join("'%s'"%k for k in revisar)):
    add(c,'Año',a,'',f'El año no coincide con la fecha del documento («{f}»). Confirmar cuál vale; si es material de época guardado para un proyecto posterior, dejarlo como está.','Revisar')
# 2. Autores
DESC=re.compile(r'equipo de guionistas|guionistas de La Isla|entrevista|Entre paréntesis|Teatro Municipal|Films Society|Allende/|Mignona',re.I)
for c,a in q("select codigo, autor from registro where autor is not null and autor<>'' order by caja_id, numero"):
    if DESC.search(a):
        m=re.search(r'conformado por (.*?)\.?$',a) or re.search(r'fueron (.*?)(\.|$)',a)
        if m:
            nombres=re.split(r',\s*|\s+y\s+',m.group(1).strip().rstrip('.'))
            add(c,'Autor',a,'; '.join(n.strip() for n in nombres if n.strip()),'El campo Autor tiene una oración. Dejar solo los nombres separados por «; » y pasar la oración al Resumen si aporta información.')
        elif len(a)>60 or 'entrevista' in a.lower() or 'paréntesis' in a.lower():
            add(c,'Autor',a,'','El campo Autor tiene una descripción en lugar de nombres. Dejar solo los nombres separados por «; » y pasar el resto al Resumen.','Revisar')
        continue
    if 'Ferreira' in a or '?' in a:
        add(c,'Autor',a,'Aída Bortnik' if '?' in a else '','Confirmar la forma del nombre' + (' (¿el segundo apellido es correcto?)' if 'Ferreira' in a else ' (tiene signos de duda)') + '.','Revisar'); continue
    n=a
    n=re.sub(r'\bA[ií]da Bort(ink|nik)\b|\bAída Bornik\b|\bAida Bortnik\b|\bBortnik,? A[ií]da\b','Aída Bortnik',n)
    n=re.sub(r'(?<![\wÁÉÍÓÚáéíóúñÑ])AB(?![\wÁÉÍÓÚáéíóúñÑ])','Aída Bortnik',n)
    if n.strip() in ('Aída','Aida','Bortnik'): n='Aída Bortnik'
    if n!=a:
        add(c,'Autor',a,n,'Unificar la forma del nombre: «Aída Bortnik», con tilde y sin abreviar.')
# 3. Nombres de cajas
for num,nom,sug,mot in [(5,'Noticias de un secuetro','Noticia de un secuestro','Error de tipeo: es la misma obra que la caja 3.'),
                        (12,'La Vìctima','La Víctima','Tilde invertida (ì en lugar de í).'),
                        (39,'Crecer do Golpe','Crecer de golpe','Error de tipeo en el título de la película.'),
                        (50,'La mujer del Cholo, Felíz cumpleaños, Como en los Cuentos, Gente Sola','La mujer del Cholo, Feliz cumpleaños, Como en los cuentos, Gente sola','«Feliz» va sin tilde.')]:
    add(str(num),'Nombre de caja (columna B)',nom,sug,mot+' Corregir en todas las filas de la pestaña.')
# 4. Descriptor basura
add('C6R10','Descriptor','g','','Hay una letra suelta en un Descriptor. Completar el descriptor que corresponda o dejar la celda vacía.','Revisar')
filas.sort(key=lambda f:(f['caja'], f['registro']))
json.dump(filas,open('filas.json','w'),ensure_ascii=False,indent=0)
from collections import Counter
print(len(filas), Counter((f['columna'],f['accion']) for f in filas))
for f in filas[:3]+[x for x in filas if x['columna']=='Autor'][:6]: print(f)
