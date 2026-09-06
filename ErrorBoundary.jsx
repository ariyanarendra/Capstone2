import { Component } from 'react'

export default class ErrorBoundary extends Component {
  constructor(props) {
    super(props)
    this.state = { hasError: false }
  }

  static getDerivedStateFromError() {
    return { hasError: true }
  }

  componentDidCatch(error) {
    console.error('GateKampus page error:', error)
  }

  render() {
    if (this.state.hasError) {
      return (
        <section className="page-fallback">
          <strong>Halaman gagal ditampilkan</strong>
          <p>Terjadi kesalahan pada tampilan. Muat ulang halaman untuk mencoba kembali.</p>
          <button onClick={() => window.location.reload()}>Muat ulang halaman</button>
        </section>
      )
    }

    return this.props.children
  }
}
