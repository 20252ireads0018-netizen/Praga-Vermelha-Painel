"""Narração com voz neural (edge-tts) para um vídeo de roteiros.json; atualiza só esse vídeo em linha.json.
Uso, na raiz do projeto: python deploy/videos/narrar_neural.py cavaleiro [voz] [velocidade] [tom]
Padrão: pt-BR-AntonioNeural, -8%, -6Hz (grave e mais lento, para história). Requer: pip install edge-tts (usa a internet).
"""
import asyncio, json, os, sys

import edge_tts

DIR = os.path.dirname(os.path.abspath(__file__))
VIDEO = sys.argv[1] if len(sys.argv) > 1 else 'cavaleiro'
VOZ = sys.argv[2] if len(sys.argv) > 2 else 'pt-BR-AntonioNeural'
VEL = sys.argv[3] if len(sys.argv) > 3 else '-8%'
TOM = sys.argv[4] if len(sys.argv) > 4 else '-6Hz'
KBPS = 48  # formato padrão do edge-tts: mp3 24 kHz, 48 kbit/s, mono (taxa constante)


async def main():
    roteiro = json.load(open(os.path.join(DIR, 'roteiros.json'), encoding='utf-8'))[VIDEO]
    caminho = os.path.join(DIR, 'linha.json')
    linha = json.load(open(caminho, encoding='utf-8-sig'))
    lista = []
    for i, c in enumerate(roteiro):
        nome = f'{VIDEO}-{i:02d}.mp3'
        await edge_tts.Communicate(c['fala'], VOZ, rate=VEL, pitch=TOM).save(os.path.join(DIR, nome))
        dur = round(os.path.getsize(os.path.join(DIR, nome)) * 8 / (KBPS * 1000), 3)
        lista.append({'cena': c['cena'], 'fala': c['fala'], 'audio': nome, 'dur': dur})
    linha[VIDEO] = lista
    json.dump(linha, open(caminho, 'w', encoding='utf-8'), ensure_ascii=False, indent=2)
    print(f'{VIDEO}: {len(lista)} cenas, {sum(x["dur"] for x in lista):.1f} s, voz {VOZ}')


asyncio.run(main())
